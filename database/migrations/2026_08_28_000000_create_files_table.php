<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('komando.files.migrate_file_table', true) || Schema::hasTable('files')) {
            return;
        }

        Schema::create('files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('mime_type');
            $table->string('extension');
            $table->unsignedBigInteger('size');
            $table->json('metadata')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! config('komando.files.migrate_file_table', true)) {
            return;
        }

        Schema::dropIfExists('files');
    }
};
