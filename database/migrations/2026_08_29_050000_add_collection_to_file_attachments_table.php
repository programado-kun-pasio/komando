<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = strval(config('komando.files.attachment_table', 'file_attachments'));

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('collection')->nullable()->after('slot');
            $table->index(
                ['attachable_type', 'attachable_id', 'collection'],
                'file_attachments_collection_index',
            );
        });
    }

    public function down(): void
    {
        $tableName = strval(config('komando.files.attachment_table', 'file_attachments'));

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex('file_attachments_collection_index');
            $table->dropColumn('collection');
        });
    }
};
