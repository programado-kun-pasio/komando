<?php declare(strict_types=1);

namespace Programado\Komando\Providers;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Programado\Komando\Console\Commands\SyncDatabaseCommand;
use Programado\Komando\Files\Contracts\StoredFileFactoryContract;
use Programado\Komando\Files\Http\Controllers\DownloadFileController;
use Programado\Komando\Files\Services\DefaultStoredFileFactory;
use Programado\Komando\Files\Services\RollbackCleanupRegistry;

final class KomandoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/komando.php', 'komando');
        $this->commands(SyncDatabaseCommand::class);
        $this->app->singleton(RollbackCleanupRegistry::class);
        $this->app->bind(StoredFileFactoryContract::class, function ($app): StoredFileFactoryContract {
            $factoryClass = $app['config']->get('komando.files.factory', DefaultStoredFileFactory::class);

            return $app->make($factoryClass);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'komando');

        if (config('komando.files.enabled', false)) {
            $this->bootFiles();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/komando.php' => config_path('komando.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../../resources/graphql/fileAttachments.graphql' => base_path('graphql/fileAttachments.graphql'),
            ], 'komando-files-graphql');
        }
    }

    private function bootFiles(): void
    {
        if (config('komando.files.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $rollbackCleanupRegistry = $this->app->make(RollbackCleanupRegistry::class);

        $this->app['events']->listen(
            TransactionCommitted::class,
            static fn (TransactionCommitted $event) => $rollbackCleanupRegistry->committed($event->connection),
        );
        $this->app['events']->listen(
            TransactionRolledBack::class,
            static fn (TransactionRolledBack $event) => $rollbackCleanupRegistry->rolledBack($event->connection),
        );
        $this->app['events']->listen(
            RegisterDirectiveNamespaces::class,
            static fn (): string => 'Programado\\Komando\\Files\\GraphQL\\Directives',
        );

        if (config('komando.files.download.enabled', true)) {
            Route::middleware(config('komando.files.download.middleware', []))
                ->get(
                    strval(config('komando.files.download.path', 'api/files/{file}/download')),
                    DownloadFileController::class,
                )
                ->name('komando.files.download');
        }
    }
}
