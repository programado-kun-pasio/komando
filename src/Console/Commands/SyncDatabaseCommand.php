<?php declare(strict_types=1);

namespace Programado\Komando\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Spatie\Ssh\Ssh;
use Illuminate\Support\Facades\Process;

class SyncDatabaseCommand extends Command
{
    protected $signature = 'komando:sync:database {db-connections=mysql : The local database connection names (comma seperated) to sync} {ssh-user=app : The SSH user of the source system} {ssh-host : The SSH host of the source system}';

    protected $description = 'Fetches the DB from remote and syncs it with the local db';

    private array $requiredCommands = [
        'local' => ['scp', '7z', 'mysql'],
        'remote' => ['mysqldump', '7z'],
    ];

    public function handle(): void
    {
        if (!$this->checkRequiredCommands()) {
            $this->error('Not all required commands are available. Aborting sync.');
            return;
        }

        $connections = explode(',', $this->argument('db-connections'));

        foreach ($connections as $connection) {
            $this->info("Starting database sync for: {$connection}");

            $this->importDatabase($connection);
        }
    }

    protected function importDatabase(string $connection): void
    {
        $config = config("database.connections.{$connection}");
        $database = $config['database'];

        $this->info("Starting database sync for {$database}...");

        // Create database dump
        $this->info("Creating dump {$database}.sql...");
        $this->sshExec("mysqldump -h 127.0.0.1 -u default --skip-lock-tables {$database} > {$database}.sql");

        // Compress dump
        $this->info("Compressing dump {$database}.sql to {$database}.sql.7z...");
        $this->sshExec("7z a -mx=9 {$database}.sql.7z {$database}.sql");
        $this->sshExec("rm {$database}.sql");

        // Copy dump locally
        $this->info("Copying {$database} dump...");
        Process::run("scp {$this->argument('ssh-user')}@{$this->argument('ssh-host')}:{$database}.sql.7z .")->throw();
        $this->sshExec("rm {$database}.sql.7z");

        // Extract dump locally
        $this->info("Importing {$database} dump locally...");
        Process::run("7z x -aoa {$database}.sql.7z")->throw();
        File::delete("{$database}.sql.7z");

        $this->call('db:wipe', [
            '--database' => $connection,
            '--force' => !App::environment('production'),
        ]);

        Process::run("mysql -h {$config['host']} -u {$config['username']} -p'{$config['password']}' {$database} < {$database}.sql")->throw();

        File::delete("{$database}.sql");

        // Run migrations
        $this->info("Running migrations...");
        $this->call('migrate', ['--force' => true, '--step' => true]);

        $this->info("Database sync completed successfully for {$database}!");
    }

    protected function checkRequiredCommands(): bool
    {
        $this->info('Checking required commands...');

        $allCommandsAvailable = true;

        // Check local commands
        $this->info('Checking local commands...');
        foreach ($this->requiredCommands['local'] as $command) {
            if (!$this->isLocalCommandAvailable($command)) {
                $this->error("Required local command '{$command}' is not available.");
                $allCommandsAvailable = false;
            } else {
                $this->line("✓ Local command '{$command}' is available.");
            }
        }

        $this->info('Checking remote commands...');
        foreach ($this->requiredCommands['remote'] as $command) {
            if (!$this->isRemoteCommandAvailable($command)) {
                $this->error("Required remote command '{$command}' is not available on {$this->argument('ssh-host')}.");
                $allCommandsAvailable = false;
            } else {
                $this->line("✓ Remote command '{$command}' is available on {$this->argument('ssh-host')}.");
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
        return Ssh::create($this->argument('ssh-user'), $this->argument('ssh-host'))->execute($command);
    }
}
