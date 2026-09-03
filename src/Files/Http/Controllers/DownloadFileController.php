<?php declare(strict_types=1);

namespace Programado\Komando\Files\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Programado\Komando\Files\Contracts\StoredFileContract;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadFileController
{
    public function __invoke(string $file): BinaryFileResponse|StreamedResponse
    {
        $modelClass = config('komando.files.file_model');

        if (! is_string($modelClass)
            || ! is_subclass_of($modelClass, Model::class)
            || ! is_subclass_of($modelClass, StoredFileContract::class)) {
            throw new LogicException('komando.files.file_model must be an Eloquent model implementing StoredFileContract.');
        }

        /** @var Model&StoredFileContract $storedFile */
        $storedFile = $modelClass::query()->findOrFail($file);
        $extension = strval($storedFile->getAttribute('extension'));
        $downloadName = $storedFile->storageName().($extension === '' ? '' : ".{$extension}");
        $mimeType = strval($storedFile->getAttribute('mime_type'));

        $diskName = strval(config('komando.files.disk', 'files'));
        $disk = Storage::disk($diskName);
        $headers = $mimeType === '' ? [] : ['Content-Type' => $mimeType];

        return match (config("filesystems.disks.{$diskName}.driver")) {
            'local' => response()->download(
                $disk->path($storedFile->storageName()),
                $downloadName,
                $headers,
            ),
            default => $disk->download(
                $storedFile->storageName(),
                $downloadName,
                $headers,
            ),
        };
    }
}
