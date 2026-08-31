<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Programado\Komando\Files\Contracts\StoredFileFactoryContract;
use Programado\Komando\Files\Events\StoredFileStored;
use Programado\Komando\Files\GraphQL\Directives\FileAttachmentDirective;
use Programado\Komando\Files\GraphQL\Directives\FileAttachmentsDirective;
use Programado\Komando\Files\Models\File;
use Programado\Komando\Files\Services\FileAttachmentService;
use Programado\Komando\Tests\Fixtures\TestFile;
use Programado\Komando\Tests\Fixtures\TestOwner;
use Programado\Komando\Tests\TestCase;
use RuntimeException;

enum TestFileSlot: string
{
    case LOGO_LIGHT = 'LOGO_LIGHT';
}

final class FileAttachmentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('files');
    }

    public function test_the_package_migration_creates_and_removes_the_attachment_table(): void
    {
        $this->assertTrue(Schema::hasTable('file_attachments'));
        $this->assertTrue(Schema::hasColumns('file_attachments', [
            'id',
            'file_id',
            'attachable_type',
            'attachable_id',
            'slot',
            'collection',
            'created_at',
            'updated_at',
        ]));

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_08_29_000000_create_file_attachments_table.php';
        $migration->down();

        $this->assertFalse(Schema::hasTable('file_attachments'));
    }

    public function test_the_package_migration_creates_and_removes_the_file_table(): void
    {
        $this->assertTrue(Schema::hasTable('files'));
        $this->assertTrue(Schema::hasColumns('files', [
            'id',
            'name',
            'mime_type',
            'extension',
            'size',
            'metadata',
            'created_at',
            'updated_at',
        ]));

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_08_28_000000_create_files_table.php';
        $migration->down();

        $this->assertFalse(Schema::hasTable('files'));
    }

    public function test_the_file_table_migration_can_be_disabled_without_removing_an_existing_table(): void
    {
        config()->set('komando.files.migrate_file_table', false);

        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_08_28_000000_create_files_table.php';
        $migration->down();
        $migration->up();

        $this->assertTrue(Schema::hasTable('files'));
    }

    public function test_the_default_model_and_a_custom_file_model_are_supported(): void
    {
        $defaultFile = app(StoredFileFactoryContract::class)->create(
            UploadedFile::fake()->create('default.pdf', 12, 'application/pdf'),
        );

        $this->assertInstanceOf(File::class, $defaultFile);
        $this->assertSame([], $defaultFile->metadata);

        config()->set('komando.files.file_model', TestFile::class);

        $customFile = app(StoredFileFactoryContract::class)->create(
            UploadedFile::fake()->create('custom.pdf', 12, 'application/pdf'),
        );

        $this->assertInstanceOf(TestFile::class, $customFile);
    }

    public function test_storing_a_file_dispatches_an_event_after_commit_but_not_after_rollback(): void
    {
        $owner = TestOwner::query()->create(['name' => 'Acme']);
        $storedFileIds = collect();

        Event::listen(
            StoredFileStored::class,
            static function (StoredFileStored $event) use ($storedFileIds): void {
                $storedFileIds->push($event->file->getKey());
            },
        );

        $committedFile = app(FileAttachmentService::class)->add(
            $owner,
            UploadedFile::fake()->create('committed.pdf'),
            'DOCUMENTS',
        );

        $this->assertSame([$committedFile->getKey()], $storedFileIds->all());

        try {
            DB::transaction(function () use ($owner): void {
                app(FileAttachmentService::class)->add(
                    $owner,
                    UploadedFile::fake()->create('rolled-back.pdf'),
                    'DOCUMENTS',
                );

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // The event must not be delivered for the rolled back file.
        }

        $this->assertSame([$committedFile->getKey()], $storedFileIds->all());
    }

    public function test_the_default_file_model_exposes_the_configured_download_route(): void
    {
        $owner = TestOwner::query()->create(['name' => 'Acme']);
        $file = app(FileAttachmentService::class)->add(
            $owner,
            UploadedFile::fake()->create('download.pdf', 12, 'application/pdf'),
            'DOCUMENTS',
        );

        $this->assertSame($file->storageName(), $file->name());
        $this->assertSame(Storage::disk('files')->path($file->storageName()), $file->path());

        $this->get($file->url())
            ->assertOk()
            ->assertDownload("{$file->storageName()}.pdf");
    }

    public function test_it_creates_replaces_and_removes_a_named_slot(): void
    {
        $owner = TestOwner::query()->create(['name' => 'Acme']);
        $service = app(FileAttachmentService::class);

        $original = $service->replace($owner, TestFileSlot::LOGO_LIGHT, UploadedFile::fake()->image('original.png'));

        $this->assertSame($original->getKey(), $owner->fileForSlot(TestFileSlot::LOGO_LIGHT)?->getKey());
        Storage::disk('files')->assertExists($original->storageName());

        $replacement = $service->replace(
            $owner,
            TestFileSlot::LOGO_LIGHT,
            UploadedFile::fake()->image('replacement.png'),
        );

        $this->assertSame($replacement->getKey(), $owner->fileForSlot(TestFileSlot::LOGO_LIGHT)?->getKey());
        $this->assertDatabaseMissing('files', ['id' => $original->getKey()]);
        Storage::disk('files')->assertMissing($original->storageName());

        $service->removeSlot($owner, TestFileSlot::LOGO_LIGHT);

        $this->assertNull($owner->fileForSlot(TestFileSlot::LOGO_LIGHT));
        $this->assertDatabaseMissing('files', ['id' => $replacement->getKey()]);
        Storage::disk('files')->assertMissing($replacement->storageName());
    }

    public function test_collection_removal_is_scoped_to_owner_slot_and_plural_attachments(): void
    {
        $firstOwner = TestOwner::query()->create(['name' => 'First']);
        $secondOwner = TestOwner::query()->create(['name' => 'Second']);
        $service = app(FileAttachmentService::class);
        $firstFile = $service->add($firstOwner, UploadedFile::fake()->create('first.pdf'), 'DOCUMENTS');
        $foreignFile = $service->add($secondOwner, UploadedFile::fake()->create('foreign.pdf'), 'DOCUMENTS');
        $otherCollectionFile = $service->add($firstOwner, UploadedFile::fake()->image('image.png'), 'IMAGES');
        $namedFile = $service->replace($firstOwner, 'LOGO', UploadedFile::fake()->image('logo.png'));

        $service->remove($firstOwner, collect([
            $firstFile->getKey(),
            $foreignFile->getKey(),
            $otherCollectionFile->getKey(),
            $namedFile->getKey(),
        ]), 'DOCUMENTS');

        $this->assertDatabaseMissing('file_attachments', ['file_id' => $firstFile->getKey()]);
        $this->assertDatabaseHas('file_attachments', ['file_id' => $foreignFile->getKey()]);
        $this->assertDatabaseHas('file_attachments', ['file_id' => $otherCollectionFile->getKey()]);
        $this->assertDatabaseHas('file_attachments', [
            'file_id' => $namedFile->getKey(),
            'slot' => 'LOGO',
        ]);
    }

    public function test_a_rollback_removes_new_files_and_preserves_replaced_files(): void
    {
        $owner = TestOwner::query()->create(['name' => 'Acme']);
        $service = app(FileAttachmentService::class);
        $original = $service->replace($owner, 'LOGO', UploadedFile::fake()->image('original.png'));

        try {
            DB::transaction(function () use ($owner, $service): void {
                $service->replace($owner, 'LOGO', UploadedFile::fake()->image('replacement.png'));

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // Assertions below verify the rollback state.
        }

        $owner->refresh();

        $this->assertSame($original->getKey(), $owner->fileForSlot('LOGO')?->getKey());
        $this->assertSame([$original->storageName()], Storage::disk('files')->allFiles());
        $this->assertSame(1, File::query()->count());
    }

    public function test_a_nested_rollback_only_cleans_up_files_created_in_the_nested_transaction(): void
    {
        $owner = TestOwner::query()->create(['name' => 'Acme']);
        $service = app(FileAttachmentService::class);

        DB::transaction(function () use ($owner, $service): void {
            $outerFile = $service->add($owner, UploadedFile::fake()->create('outer.pdf'), 'DOCUMENTS');

            try {
                DB::transaction(function () use ($owner, $service): void {
                    $service->add($owner, UploadedFile::fake()->create('inner.pdf'), 'DOCUMENTS');

                    throw new RuntimeException('nested rollback');
                });
            } catch (RuntimeException) {
                // Assertions below verify cleanup at the correct transaction level.
            }

            $this->assertDatabaseHas('files', ['id' => $outerFile->getKey()]);
            $this->assertSame([$outerFile->storageName()], Storage::disk('files')->allFiles());
        });

        $this->assertDatabaseCount('files', 1);
        $this->assertDatabaseCount('file_attachments', 1);
    }

    public function test_a_file_is_deleted_only_after_its_last_attachment_is_removed(): void
    {
        $firstOwner = TestOwner::query()->create(['name' => 'First']);
        $secondOwner = TestOwner::query()->create(['name' => 'Second']);
        $service = app(FileAttachmentService::class);
        $file = $service->add($firstOwner, UploadedFile::fake()->create('shared.pdf'), 'DOCUMENTS');
        $secondOwner->fileAttachments()->create([
            'file_id' => $file->getKey(),
            'slot' => null,
            'collection' => 'DOCUMENTS',
        ]);

        $this->assertFalse($file->delete());

        $service->remove($firstOwner, collect([$file->getKey()]), 'DOCUMENTS');

        $this->assertDatabaseHas('files', ['id' => $file->getKey()]);
        Storage::disk('files')->assertExists($file->storageName());

        $service->remove($secondOwner, collect([$file->getKey()]), 'DOCUMENTS');

        $this->assertDatabaseMissing('files', ['id' => $file->getKey()]);
        Storage::disk('files')->assertMissing($file->storageName());
    }

    public function test_committed_rollback_cleanups_do_not_leak_into_later_transactions(): void
    {
        $owner = TestOwner::query()->create(['name' => 'Acme']);
        $file = app(FileAttachmentService::class)->add(
            $owner,
            UploadedFile::fake()->create('committed.pdf'),
            'DOCUMENTS',
        );

        try {
            DB::transaction(static function (): void {
                throw new RuntimeException('unrelated rollback');
            });
        } catch (RuntimeException) {
            // The committed file must not be part of this later rollback.
        }

        $this->assertDatabaseHas('files', ['id' => $file->getKey()]);
        Storage::disk('files')->assertExists($file->storageName());
    }

    public function test_an_invalid_upload_does_not_create_a_file_or_attachment(): void
    {
        $owner = TestOwner::query()->create(['name' => 'Acme']);
        $upload = new UploadedFile(
            __FILE__,
            'broken.pdf',
            'application/pdf',
            UPLOAD_ERR_PARTIAL,
            true,
        );

        try {
            app(FileAttachmentService::class)->add($owner, $upload, 'DOCUMENTS');
            $this->fail('An invalid upload should throw an exception.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('partially uploaded', $exception->getMessage());
        }

        $this->assertDatabaseCount('files', 0);
        $this->assertDatabaseCount('file_attachments', 0);
        $this->assertSame([], Storage::disk('files')->allFiles());
    }

    public function test_deleting_an_unattached_file_removes_its_physical_file(): void
    {
        $owner = TestOwner::query()->create(['name' => 'Acme']);
        $file = app(FileAttachmentService::class)->add(
            $owner,
            UploadedFile::fake()->create('temporary.pdf'),
            'DOCUMENTS',
        );
        $owner->fileAttachments()->delete();

        $this->assertTrue($file->delete());
        $this->assertDatabaseMissing('files', ['id' => $file->getKey()]);
        Storage::disk('files')->assertMissing($file->storageName());
    }

    public function test_the_provider_registers_directives_and_the_slot_type_is_configurable(): void
    {
        config()->set('komando.files.graphql_slot_type', 'FileSlot');

        $namespaces = app('events')->dispatch(new RegisterDirectiveNamespaces);

        $this->assertContains('Programado\\Komando\\Files\\GraphQL\\Directives', $namespaces);
        $this->assertStringContainsString('slot: FileSlot!', FileAttachmentDirective::definition());
        $this->assertStringContainsString('slot: FileSlot!', FileAttachmentsDirective::definition());
    }

    public function test_an_invalid_graphql_slot_type_is_rejected(): void
    {
        config()->set('komando.files.graphql_slot_type', 'FileSlot!');

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('must be a valid GraphQL type name');

        FileAttachmentDirective::definition();
    }
}
