<?php declare(strict_types=1);

namespace Programado\Komando\Files\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;
use Programado\Komando\Files\Contracts\StoredFileContract;

/**
 * @property int $id
 * @property int|string $file_id
 * @property int|string $attachable_id
 * @property string $attachable_type
 * @property ?string $slot
 * @property Model&StoredFileContract $file
 * @property Model $attachable
 */
class FileAttachment extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return strval(config('komando.files.attachment_table', 'file_attachments'));
    }

    public function file(): BelongsTo
    {
        $fileModel = config('komando.files.file_model');

        if (! is_string($fileModel)
            || ! is_subclass_of($fileModel, Model::class)
            || ! is_subclass_of($fileModel, StoredFileContract::class)) {
            throw new LogicException('komando.files.file_model must be an Eloquent model implementing StoredFileContract.');
        }

        return $this->belongsTo($fileModel);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
