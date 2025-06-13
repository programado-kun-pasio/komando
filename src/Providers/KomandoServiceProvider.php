<?php declare(strict_types=1);

namespace Programado\Komando\Providers;

use Illuminate\Support\ServiceProvider;
use Programado\Komando\Console\Commands\SyncDatabaseCommand;

final class KomandoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands(SyncDatabaseCommand::class);
    }
}