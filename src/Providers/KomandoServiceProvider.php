<?php declare(strict_types=1);

namespace Programado\Komando\Providers;

use Illuminate\Support\ServiceProvider;
use Programado\Komando\Console\Commands\SyncDatabaseCommand;
use Programado\Komando\Console\Commands\UpdateGraphQLDevSchema;

final class KomandoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/komando.php', 'komando');
        $this->commands(SyncDatabaseCommand::class);
        $this->commands(UpdateGraphQLDevSchema::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/komando.php' => config_path('komando.php'),
            ], 'config');
        }
    }
}