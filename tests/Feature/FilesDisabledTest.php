<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Feature;

use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Orchestra\Testbench\TestCase;
use Programado\Komando\Providers\KomandoServiceProvider;

final class FilesDisabledTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [KomandoServiceProvider::class];
    }

    public function test_the_file_module_does_not_register_migrations_or_directives_by_default(): void
    {
        $migrationPath = realpath(dirname(__DIR__, 2).'/database/migrations');
        $directiveNamespaces = app('events')->dispatch(new RegisterDirectiveNamespaces);

        $this->assertNotContains($migrationPath, app('migrator')->paths());
        $this->assertNotContains('Programado\\Komando\\Files\\GraphQL\\Directives', $directiveNamespaces);
    }
}
