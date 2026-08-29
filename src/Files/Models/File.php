<?php declare(strict_types=1);

namespace Programado\Komando\Files\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Programado\Komando\Files\Contracts\StoredFileContract;
use Programado\Komando\Files\Traits\IsStoredFile;

/**
 * @property string $id
 * @property string $name
 * @property string $mime_type
 * @property string $extension
 * @property int $size
 * @property array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, FileAttachment> $attachments
 */
class File extends Model implements StoredFileContract
{
    use HasUlids, IsStoredFile;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function name(): string
    {
        return $this->storageName();
    }

    public function path(): string
    {
        return Storage::disk(strval(config('komando.files.disk', 'files')))->path($this->storageName());
    }

    public function url(): string
    {
        return route('komando.files.download', [
            'file' => $this->getRouteKey(),
            'v' => $this->updated_at->timestamp,
        ]);
    }
}
