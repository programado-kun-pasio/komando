<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Programado\Komando\Files\Contracts\StoredFileContract;

return new class extends Migration
{
    public function up(): void
    {
        $fileModel = config('komando.files.file_model');

        if (! is_string($fileModel)
            || ! is_subclass_of($fileModel, Model::class)
            || ! is_subclass_of($fileModel, StoredFileContract::class)) {
            throw new LogicException('komando.files.file_model must be an Eloquent model implementing StoredFileContract.');
        }

        Schema::create(strval(config('komando.files.attachment_table', 'file_attachments')), function (Blueprint $table) use ($fileModel): void {
            $table->id();
            $table->foreignIdFor($fileModel, 'file_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');
            $table->string('slot')->nullable();
            $table->string('collection')->nullable();
            $table->timestamps();

            $table->unique(['attachable_type', 'attachable_id', 'slot'], 'file_attachments_slot_unique');
            $table->index(
                ['attachable_type', 'attachable_id', 'collection'],
                'file_attachments_collection_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(strval(config('komando.files.attachment_table', 'file_attachments')));
    }
};
