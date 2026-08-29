<?php declare(strict_types=1);

namespace Programado\Komando\Files\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Programado\Komando\Files\Models\FileAttachment;

/** @mixin Model */
trait IsStoredFile
{
    public static function bootIsStoredFile(): void
    {
        static::deleting(function (Model $file): bool {
            if ($file->attachments()->exists()) {
                return false;
            }

            $disk = Storage::disk(strval(config('komando.files.disk', 'files')));
            $storageName = $file->storageName();

            return ! $disk->exists($storageName) || $disk->delete($storageName);
        });
    }

    public function attachments(): HasMany
    {
        $attachmentModel = config('komando.files.attachment_model', FileAttachment::class);

        if (! is_string($attachmentModel) || ! is_a($attachmentModel, FileAttachment::class, true)) {
            throw new LogicException('komando.files.attachment_model must extend FileAttachment.');
        }

        return $this->hasMany($attachmentModel, 'file_id');
    }

    public function storageName(): string
    {
        return strval($this->getKey());
    }
}
