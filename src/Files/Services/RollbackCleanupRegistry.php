<?php declare(strict_types=1);

namespace Programado\Komando\Files\Services;

use Closure;
use Illuminate\Database\Connection;

final class RollbackCleanupRegistry
{
    /** @var array<int, list<array{level: int, callback: Closure}>> */
    private array $callbacks = [];

    public function register(Connection $connection, Closure $callback): void
    {
        $connectionId = spl_object_id($connection);
        $this->callbacks[$connectionId][] = [
            'level' => $connection->transactionLevel(),
            'callback' => $callback,
        ];
    }

    public function committed(Connection $connection): void
    {
        $connectionId = spl_object_id($connection);
        $transactionLevel = $connection->transactionLevel();

        if ($transactionLevel === 0) {
            unset($this->callbacks[$connectionId]);

            return;
        }

        $this->callbacks[$connectionId] = collect($this->callbacks[$connectionId] ?? [])
            ->map(static function (array $entry) use ($transactionLevel): array {
                $entry['level'] = min($entry['level'], $transactionLevel);

                return $entry;
            })
            ->all();
    }

    public function rolledBack(Connection $connection): void
    {
        $connectionId = spl_object_id($connection);
        $transactionLevel = $connection->transactionLevel();
        $callbacks = collect($this->callbacks[$connectionId] ?? []);

        $callbacks
            ->filter(static fn (array $entry): bool => $entry['level'] > $transactionLevel)
            ->each(static fn (array $entry) => ($entry['callback'])());

        $remainingCallbacks = $callbacks
            ->reject(static fn (array $entry): bool => $entry['level'] > $transactionLevel)
            ->values()
            ->all();

        if ($remainingCallbacks === []) {
            unset($this->callbacks[$connectionId]);

            return;
        }

        $this->callbacks[$connectionId] = $remainingCallbacks;
    }
}
