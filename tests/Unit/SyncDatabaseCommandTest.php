<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Unit;

use Programado\Komando\Console\Commands\SyncDatabaseCommand;
use Programado\Komando\Tests\TestCase;

final class SyncDatabaseCommandTest extends TestCase
{
    public function test_mariadb_requires_mysql_client_commands(): void
    {
        config()->set('komando.database_sync.connections', ['mariadb']);
        config()->set('komando.database_sync.commands.local', ['scp', '7z']);
        config()->set('komando.database_sync.commands.remote', ['7z']);
        config()->set('database.connections.mariadb.driver', 'mariadb');

        $commands = $this->command()->requiredCommands();

        $this->assertSame(['scp', '7z', 'mysql'], $commands['local']);
        $this->assertSame(['7z', 'mysqldump'], $commands['remote']);
    }

    public function test_mariadb_uses_mysqldump(): void
    {
        config()->set('komando.database_sync.remote_database.password', 'secret');
        config()->set('komando.database_sync.mysqldump.options', ['--skip-lock-tables']);

        $command = $this->command()->remoteDumpCommand(
            'mariadb',
            'lernado',
            'database',
            'root',
        );

        $this->assertSame(
            "MYSQL_PWD='secret' mysqldump -h database -u root --skip-lock-tables lernado > lernado.sql",
            $command,
        );
    }

    public function test_mariadb_uses_mysql_for_local_import(): void
    {
        $command = $this->command()->localImportCommand('mariadb', [
            'host' => 'database',
            'port' => 3306,
            'username' => 'root',
            'password' => 'secret',
        ], 'lernado');

        $this->assertSame(
            "MYSQL_PWD='secret' mysql -h 'database' -P '3306' -u 'root' 'lernado' < 'lernado.sql'",
            $command,
        );
    }

    private function command(): TestableSyncDatabaseCommand
    {
        return new TestableSyncDatabaseCommand;
    }
}

final class TestableSyncDatabaseCommand extends SyncDatabaseCommand
{
    public function requiredCommands(): array
    {
        return $this->getRequiredCommands();
    }

    public function remoteDumpCommand(
        string $driver,
        string $database,
        string $remoteDbHost,
        string $remoteDbUser,
    ): string {
        return $this->buildRemoteDumpCommand($driver, $database, $remoteDbHost, $remoteDbUser);
    }

    public function localImportCommand(string $driver, array $config, string $database): string
    {
        return $this->buildLocalImportCommand($driver, $config, $database);
    }
}
