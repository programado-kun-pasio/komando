<?php declare(strict_types=1);

namespace Programado\Komando\Files\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use LogicException;
use Programado\Komando\Files\Contracts\StoredFileContract;
use Programado\Komando\Files\Models\FileAttachment;
use Programado\Komando\Files\Support\Slot;

/**
 * @mixin Model
 *
 * @property Collection<int, Model&StoredFileContract> $files
 * @property Collection<int, FileAttachment> $fileAttachments
 */
trait HasFileAttachments
{
    public function fileAttachments(): MorphMany
    {
        return $this->morphMany($this->attachmentModelClass(), 'attachable');
    }

    public function files(): MorphToMany
    {
        return $this->morphToMany(
            $this->fileModelClass(),
            'attachable',
            strval(config('komando.files.attachment_table', 'file_attachments')),
            relatedPivotKey: 'file_id',
        )
            ->withPivot(['id', 'slot'])
            ->withTimestamps();
    }

    /** @return (Model&StoredFileContract)|null */
    public function fileForSlot(BackedEnum|string $slot): ?Model
    {
        $slotValue = Slot::value($slot);

        if ($this->relationLoaded('fileAttachments')) {
            return $this->fileAttachments
                ->first(static fn (FileAttachment $attachment): bool => $attachment->slot === $slotValue)
                ?->file;
        }

        return $this->fileAttachments()
            ->with('file')
            ->where('slot', '=', $slotValue)
            ->first()
            ?->file;
    }

    /** @return class-string<Model&StoredFileContract> */
    private function fileModelClass(): string
    {
        $modelClass = config('komando.files.file_model');

        if (! is_string($modelClass)
            || ! is_subclass_of($modelClass, Model::class)
            || ! is_subclass_of($modelClass, StoredFileContract::class)) {
            throw new LogicException('komando.files.file_model must be an Eloquent model implementing StoredFileContract.');
        }

        return $modelClass;
    }

    /** @return class-string<FileAttachment> */
    private function attachmentModelClass(): string
    {
        $modelClass = config('komando.files.attachment_model', FileAttachment::class);

        if (! is_string($modelClass) || ! is_a($modelClass, FileAttachment::class, true)) {
            throw new LogicException('komando.files.attachment_model must extend FileAttachment.');
        }

        return $modelClass;
    }
}
