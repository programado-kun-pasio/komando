<?php declare(strict_types=1);

namespace Programado\Komando\Files\Services;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Programado\Komando\Files\Contracts\HasFileAttachmentsContract;
use Programado\Komando\Files\Contracts\StoredFileContract;
use Programado\Komando\Files\Contracts\StoredFileFactoryContract;
use Programado\Komando\Files\Events\StoredFileStored;
use Programado\Komando\Files\Models\FileAttachment;
use Programado\Komando\Files\Support\Slot;
use RuntimeException;

final readonly class FileAttachmentService
{
    public function __construct(
        private StoredFileFactoryContract $storedFileFactory,
        private RollbackCleanupRegistry $rollbackCleanupRegistry,
    ) {}

    /**
     * @param  Model&HasFileAttachmentsContract  $owner
     * @return Model&StoredFileContract
     */
    public function replace(Model $owner, BackedEnum|string $slot, UploadedFile $upload): Model
    {
        return DB::transaction(function () use ($owner, $slot, $upload): Model {
            $slotValue = Slot::value($slot);
            $previousAttachment = $owner->fileAttachments()
                ->with('file')
                ->where('slot', '=', $slotValue)
                ->first();

            $file = $this->store($upload);

            $owner->fileAttachments()->updateOrCreate(
                ['slot' => $slotValue],
                ['file_id' => $file->getKey()],
            );

            if ($previousAttachment && $previousAttachment->file_id !== $file->getKey()) {
                $this->deleteFileAfterCommitWhenUnused($previousAttachment->file);
            }

            return $file;
        });
    }

    /** @param Model&HasFileAttachmentsContract $owner */
    public function removeSlot(Model $owner, BackedEnum|string $slot): void
    {
        DB::transaction(function () use ($owner, $slot): void {
            $attachment = $owner->fileAttachments()
                ->with('file')
                ->where('slot', '=', Slot::value($slot))
                ->first();

            if (! $attachment) {
                return;
            }

            $attachment->delete();
            $this->deleteFileAfterCommitWhenUnused($attachment->file);
        });
    }

    /**
     * @param  Model&HasFileAttachmentsContract  $owner
     * @return Model&StoredFileContract
     */
    public function add(Model $owner, UploadedFile $upload): Model
    {
        return DB::transaction(function () use ($owner, $upload): Model {
            $file = $this->store($upload);

            $owner->fileAttachments()->create([
                'file_id' => $file->getKey(),
                'slot' => null,
            ]);

            return $file;
        });
    }

    /**
     * @param  Model&HasFileAttachmentsContract  $owner
     * @param  Collection<int, int|string>  $fileIds
     */
    public function remove(Model $owner, Collection $fileIds): void
    {
        DB::transaction(function () use ($owner, $fileIds): void {
            $owner->fileAttachments()
                ->with('file')
                ->whereNull('slot')
                ->whereIn('file_id', $fileIds)
                ->get()
                ->each(function (FileAttachment $attachment): void {
                    $attachment->delete();
                    $this->deleteFileAfterCommitWhenUnused($attachment->file);
                });
        });
    }

    /** @return Model&StoredFileContract */
    private function store(UploadedFile $upload): Model
    {
        throw_if(
            $upload->getError() !== UPLOAD_ERR_OK,
            $upload->getErrorMessage(),
        );

        $file = $this->storedFileFactory->create($upload);
        $diskName = strval(config('komando.files.disk', 'files'));
        $storageName = $file->storageName();
        $disk = Storage::disk($diskName);

        if ($disk->putFileAs('', $upload, $storageName) === false) {
            $disk->delete($storageName);

            throw new RuntimeException('The uploaded file could not be stored.');
        }

        $this->rollbackCleanupRegistry->register(
            DB::connection(),
            static fn () => Storage::disk($diskName)->delete($storageName),
        );

        StoredFileStored::dispatch($file);

        return $file;
    }

    /** @param Model&StoredFileContract $file */
    private function deleteFileAfterCommitWhenUnused(Model $file): void
    {
        $fileClass = $file::class;
        $fileId = $file->getKey();
        $diskName = strval(config('komando.files.disk', 'files'));

        DB::afterCommit(static function () use ($fileClass, $fileId, $diskName): void {
            $file = $fileClass::query()->find($fileId);

            if (! $file instanceof Model
                || ! $file instanceof StoredFileContract
                || $file->attachments()->exists()) {
                return;
            }

            if ($file->delete()) {
                Storage::disk($diskName)->delete($file->storageName());
            }
        });
    }
}
