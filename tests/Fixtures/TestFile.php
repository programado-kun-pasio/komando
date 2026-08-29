<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Programado\Komando\Files\Contracts\StoredFileContract;
use Programado\Komando\Files\Traits\IsStoredFile;

final class TestFile extends Model implements StoredFileContract
{
    use HasUlids, IsStoredFile;

    protected $table = 'files';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
