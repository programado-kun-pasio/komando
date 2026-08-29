<?php declare(strict_types=1);

namespace Programado\Komando\Files\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use LogicException;
use Programado\Komando\Files\Contracts\StoredFileContract;
use Programado\Komando\Files\Contracts\StoredFileFactoryContract;

final class DefaultStoredFileFactory implements StoredFileFactoryContract
{
    /** @return Model&StoredFileContract */
    public function create(UploadedFile $upload): Model
    {
        $modelClass = config('komando.files.file_model');

        if (! is_string($modelClass)
            || ! is_subclass_of($modelClass, Model::class)
            || ! is_subclass_of($modelClass, StoredFileContract::class)) {
            throw new LogicException('komando.files.file_model must be an Eloquent model implementing StoredFileContract.');
        }

        /** @var Model&StoredFileContract $file */
        $file = new $modelClass;
        $file->forceFill([
            'name' => pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME),
            'size' => $upload->getSize(),
            'mime_type' => $upload->getClientMimeType(),
            'extension' => $upload->getClientOriginalExtension(),
            'metadata' => [],
        ]);
        $file->saveOrFail();

        return $file;
    }
}
