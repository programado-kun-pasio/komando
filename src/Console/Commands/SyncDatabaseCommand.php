<?php declare(strict_types=1);

namespace Programado\Komando\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process as SymfonyProcess;

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
        $database = $config['database'] ?? null;
        $driver = $config['driver'] ?? null;

        if (empty($database) || empty($driver)) {
            throw new \InvalidArgumentException("Database connection [{$connection}] is missing a driver or database name.");
        }

        if (!in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new \InvalidArgumentException("Database driver [{$driver}] is not supported for connection [{$connection}].");
        }

        $this->info("Starting database sync for {$database}...");

        $remoteDbHost = config('komando.database_sync.remote_database.host');
        $remoteDbUser = config('komando.database_sync.remote_database.user');
        $compressionLevel = config('komando.database_sync.compression.level');
        $sshHost = config('komando.database_sync.ssh.host');
        $sshUser = config('komando.database_sync.ssh.user');
        $sshPort = config('komando.database_sync.ssh.port');
        $copyTimeout = (int) config('komando.database_sync.mysql.timeouts.copy');

        // Create database dump
        $this->info("Creating dump {$database}.sql...");
        $this->sshExec($this->buildRemoteDumpCommand($driver, $database, $remoteDbHost, $remoteDbUser));

        // Compress dump
        $this->info("Compressing dump {$database}.sql to {$database}.sql.7z...");
        $this->sshExec("7z a -mx={$compressionLevel} {$database}.sql.7z {$database}.sql");
        $this->sshExec("rm {$database}.sql");

        // Copy dump locally
        $this->info("Copying {$database} dump...");
        Process::timeout($copyTimeout)->run("scp -P {$sshPort} {$sshUser}@{$sshHost}:{$database}.sql.7z .")->throw();
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

        $importTimeout = (int) config('komando.database_sync.mysql.timeouts.import');
        Process::timeout($importTimeout)->run($this->buildLocalImportCommand($driver, $config, $database))->throw();

        File::delete("{$database}.sql");

        $this->info("Database sync completed successfully for {$database}!");

        $commandsAfterSync = config('komando.database_sync.after_sync_commands', []);

        foreach ($commandsAfterSync as $command) {
            if (is_callable($command)) {
                $this->info("Running callable");
                $command();
            } else {
                $this->info("Running command: {$command}");
                $this->call($commandsAfterSync);
            }
        }
    }

    protected function checkRequiredCommands(): bool
    {
        $this->info('Checking required commands...');

        $requiredCommands = $this->getRequiredCommands();
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

    protected function getRequiredCommands(): array
    {
        $localCommands = $this->filterDatabaseSpecificCommands(
            config('komando.database_sync.commands.local', [])
        );
        $remoteCommands = $this->filterDatabaseSpecificCommands(
            config('komando.database_sync.commands.remote', [])
        );

        foreach ((array) config('komando.database_sync.connections', []) as $connection) {
            $driver = config("database.connections.{$connection}.driver");

            if ($driver === 'pgsql') {
                $localCommands[] = 'psql';
                $remoteCommands[] = 'pg_dump';
            }

            if ($driver === 'mysql') {
                $localCommands[] = 'mysql';
                $remoteCommands[] = 'mysqldump';
            }
        }

        return [
            'local' => array_values(array_unique($localCommands)),
            'remote' => array_values(array_unique($remoteCommands)),
        ];
    }

    protected function filterDatabaseSpecificCommands(array $commands): array
    {
        return array_values(array_filter(
            $commands,
            static fn (string $command): bool => !in_array($command, ['mysql', 'mysqldump', 'psql', 'pg_dump'], true)
        ));
    }

    protected function buildRemoteDumpCommand(string $driver, string $database, string $remoteDbHost, string $remoteDbUser): string
    {
        $remoteDbPassword = config('komando.database_sync.remote_database.password');

        return match ($driver) {
            'mysql' => implode(' ', array_filter([
                $this->withPasswordEnv('MYSQL_PWD', $remoteDbPassword),
                "mysqldump -h {$remoteDbHost} -u {$remoteDbUser}",
                $this->implodeOptions(config('komando.database_sync.mysqldump.options', [])),
                $database,
            ])) . " > {$database}.sql",
            'pgsql' => implode(' ', array_filter([
                $this->withPasswordEnv('PGPASSWORD', $remoteDbPassword),
                "pg_dump -h {$remoteDbHost} -U {$remoteDbUser}",
                $this->implodeOptions(config('komando.database_sync.pg_dump.options', [])),
                $database,
            ])) . " > {$database}.sql",
        };
    }

    protected function buildLocalImportCommand(string $driver, array $config, string $database): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $port = $config['port'] ?? null;

        if (empty($username)) {
            throw new \InvalidArgumentException("Database connection for [{$database}] is missing a username.");
        }

        return match ($driver) {
            'mysql' => trim(implode(' ', array_filter([
                $this->withPasswordEnv('MYSQL_PWD', $password),
                'mysql',
                '-h',
                escapeshellarg((string) $host),
                $port ? '-P ' . escapeshellarg((string) $port) : null,
                '-u',
                escapeshellarg((string) $username),
                escapeshellarg($database),
                '< ' . escapeshellarg("{$database}.sql"),
            ]))),
            'pgsql' => trim(implode(' ', array_filter([
                $this->withPasswordEnv('PGPASSWORD', $password),
                'psql',
                '-h',
                escapeshellarg((string) $host),
                $port ? '-p ' . escapeshellarg((string) $port) : null,
                '-U',
                escapeshellarg((string) $username),
                '-d',
                escapeshellarg($database),
                '-f',
                escapeshellarg("{$database}.sql"),
            ]))),
        };
    }

    protected function withPasswordEnv(string $name, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return "{$name}='{$value}'";
    }

    protected function implodeOptions(array $options): string
    {
        return implode(' ', $options);
    }

    private function sshExec(string $command): SymfonyProcess
    {
        $sshUser = config('komando.database_sync.ssh.user');
        $sshHost = config('komando.database_sync.ssh.host');
        $sshPort = config('komando.database_sync.ssh.port');
        $sshPassword = config('komando.database_sync.ssh.password');

        $process = Ssh::create($sshUser, $sshHost, $sshPort, $sshPassword)->execute($command);

        throw_if(!$process->isSuccessful(), new \Exception($process->getErrorOutput()));

        return $process;
    }
}
