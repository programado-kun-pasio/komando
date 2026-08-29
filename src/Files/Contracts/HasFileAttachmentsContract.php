<?php declare(strict_types=1);

namespace Programado\Komando\Files\Contracts;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

interface HasFileAttachmentsContract
{
    public function fileAttachments(): MorphMany;

    public function files(): MorphToMany;

    /** @return (Model&StoredFileContract)|null */
    public function fileForSlot(BackedEnum|string $slot): ?Model;
}
