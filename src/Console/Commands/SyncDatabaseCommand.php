<?php declare(strict_types=1);

namespace Programado\Komando\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Spatie\Ssh\Ssh;
use Illuminate\Support\Facades\Process;

class SyncDatabaseCommand extends Command
{
    protected $signature = 'komando:sync:database';

    protected $description = 'Fetches the DB from remote and syncs it with the local db';


    public function handle(): void
    {
        $sshHost = config('komando.database_sync.ssh.host');
        $sshUser = config('komando.database_sync.ssh.user');
        $connections = config('komando.database_sync.connections');

        if (empty($sshHost)) {
            $this->error('SSH host is not configured. Please set KOMANDO_SSH_HOST in your .env file or publish the configuration.');
            return;
        }

        if (!$this->checkRequiredCommands()) {
            $this->error('Not all required commands are available. Aborting sync.');
            return;
        }

        foreach ($connections as $connection) {
            $this->info("Starting database sync for: {$connection}");
            $this->importDatabase($connection);
        }

        // Run migrations
        $this->info("Running migrations...");
        $this->call('migrate', ['--force' => true, '--step' => true]);
    }

    protected function importDatabase(string $connection): void
    {
        $config = config("database.connections.{$connection}");
        $database = $config['database'];

        $this->info("Starting database sync for {$database}...");

        $remoteDbHost = config('komando.database_sync.remote_database.host');
        $remoteDbUser = config('komando.database_sync.remote_database.user');
        $mysqldumpOptions = implode(' ', config('komando.database_sync.mysqldump.options'));
        $compressionLevel = config('komando.database_sync.compression.level');
        $sshHost = config('komando.database_sync.ssh.host');
        $sshUser = config('komando.database_sync.ssh.user');

        // Create database dump
        $this->info("Creating dump {$database}.sql...");
        $this->sshExec("mysqldump -h {$remoteDbHost} -u {$remoteDbUser} {$mysqldumpOptions} {$database} > {$database}.sql");

        // Compress dump
        $this->info("Compressing dump {$database}.sql to {$database}.sql.7z...");
        $this->sshExec("7z a -mx={$compressionLevel} {$database}.sql.7z {$database}.sql");
        $this->sshExec("rm {$database}.sql");

        // Copy dump locally
        $this->info("Copying {$database} dump...");
        Process::run("scp {$sshUser}@{$sshHost}:{$database}.sql.7z .")->throw();
        $this->sshExec("rm {$database}.sql.7z");

        // Extract dump locally
        $this->info("Importing {$database} dump locally...");
        Process::run("7z x -aoa {$database}.sql.7z")->throw();
        File::delete("{$database}.sql.7z");

        $allowProductionWipe = config('komando.database_sync.safety.allow_production_wipe');
        $this->call('db:wipe', [
            '--database' => $connection,
            '--force' => !App::environment('production') || $allowProductionWipe,
        ]);

        $importTimeout = config('komando.database_sync.mysql.import_timeout');
        Process::timeout($importTimeout)->run("mysql -h {$config['host']} -u {$config['username']} -p'{$config['password']}' {$database} < {$database}.sql")->throw();

        File::delete("{$database}.sql");

        $this->info("Database sync completed successfully for {$database}!");
    }

    protected function checkRequiredCommands(): bool
    {
        $this->info('Checking required commands...');

        $requiredCommands = config('komando.database_sync.commands');
        $sshHost = config('komando.database_sync.ssh.host');
        $allCommandsAvailable = true;

        // Check local commands
        $this->info('Checking local commands...');
        foreach ($requiredCommands['local'] as $command) {
            if (!$this->isLocalCommandAvailable($command)) {
                $this->error("Required local command '{$command}' is not available.");
                $allCommandsAvailable = false;
            } else {
                $this->line("✓ Local command '{$command}' is available.");
            }
        }

        $this->info('Checking remote commands...');
        foreach ($requiredCommands['remote'] as $command) {
            if (!$this->isRemoteCommandAvailable($command)) {
                $this->error("Required remote command '{$command}' is not available on {$sshHost}.");
                $allCommandsAvailable = false;
            } else {
                $this->line("✓ Remote command '{$command}' is available on {$sshHost}.");
            }
        }

        return $allCommandsAvailable;
    }

    protected function isLocalCommandAvailable(string $command): bool
    {
        $result = Process::run("which {$command}");
        return $result->successful();
    }

    protected function isRemoteCommandAvailable(string $command): bool
    {
        try {
            $process = $this->sshExec("which {$command}");
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function sshExec(string $command): \Symfony\Component\Process\Process
    {
        $sshUser = config('komando.database_sync.ssh.user');
        $sshHost = config('komando.database_sync.ssh.host');
        $sshPort = config('komando.database_sync.ssh.port');
        
        $process = Ssh::create($sshUser, $sshHost, $sshPort)->execute($command);

        throw_if(!$process->isSuccessful(), new \Exception($process->getErrorOutput()));

        return $process;
    }
}
