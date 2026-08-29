<?php declare(strict_types=1);

namespace Programado\Komando\Files\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Programado\Komando\Files\Contracts\StoredFileContract;

final class StoredFileStored implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model&StoredFileContract $file,
    ) {}
}
