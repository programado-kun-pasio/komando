<?php declare(strict_types=1);

namespace Programado\Komando\Files\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

interface StoredFileFactoryContract
{
    /** @return Model&StoredFileContract */
    public function create(UploadedFile $upload): Model;
}
