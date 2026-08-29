<?php declare(strict_types=1);

namespace Programado\Komando\Files\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface StoredFileContract
{
    public function attachments(): HasMany;

    public function storageName(): string;
}
