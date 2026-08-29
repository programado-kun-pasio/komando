<?php declare(strict_types=1);

namespace Programado\Komando\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Programado\Komando\Providers\KomandoServiceProvider;
use Programado\Komando\Tests\Fixtures\TestFile;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [KomandoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('komando.files.file_model', TestFile::class);
        $app['config']->set('komando.files.enabled', true);
        $app['config']->set('komando.files.disk', 'files');
        $app['config']->set('filesystems.disks.files', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/files'),
            'throw' => false,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('owners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('mime_type');
            $table->string('extension');
            $table->unsignedBigInteger('size');
            $table->json('metadata')->default('{}');
            $table->timestamps();
        });
    }
}
