<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Feature;

use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Orchestra\Testbench\TestCase;
use Programado\Komando\Providers\KomandoServiceProvider;

final class FilesWithoutMigrationsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [KomandoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('komando.files.enabled', true);
        $app['config']->set('komando.files.migrations', false);
    }

    public function test_directives_can_be_enabled_without_package_migrations(): void
    {
        $migrationPath = realpath(dirname(__DIR__, 2).'/database/migrations');
        $directiveNamespaces = app('events')->dispatch(new RegisterDirectiveNamespaces);

        $this->assertNotContains($migrationPath, app('migrator')->paths());
        $this->assertContains('Programado\\Komando\\Files\\GraphQL\\Directives', $directiveNamespaces);
    }
}
