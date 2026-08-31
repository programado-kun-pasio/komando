<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Feature;

use GraphQL\Error\DebugFlag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LastDragon_ru\LaraASP\Core\PackageProvider as LaraAspCoreServiceProvider;
use LastDragon_ru\LaraASP\GraphQL\PackageProvider as LaraAspGraphQLServiceProvider;
use Nuwave\Lighthouse\LighthouseServiceProvider;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Programado\Komando\Providers\KomandoServiceProvider;
use Programado\Komando\Tests\TestCase;

final class FileAttachmentGraphQLTest extends TestCase
{
    use MakesGraphQLRequests;

    protected function getPackageProviders($app): array
    {
        return [
            LighthouseServiceProvider::class,
            LaraAspCoreServiceProvider::class,
            LaraAspGraphQLServiceProvider::class,
            KomandoServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.debug', true);
        $app['config']->set('lighthouse.schema_path', dirname(__DIR__).'/Fixtures/file-attachments.graphql');
        $app['config']->set('lighthouse.debug', DebugFlag::INCLUDE_DEBUG_MESSAGE);
        $app['config']->set('lighthouse.namespaces.scalars', ['Nuwave\\Lighthouse\\Schema\\Types\\Scalars']);
        $app['config']->set('lighthouse.schema_cache.enable', false);
        $app['config']->set('lighthouse.query_cache.enable', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('files');
    }

    public function test_graphql_can_create_and_resolve_named_and_unassigned_files(): void
    {
        $response = $this->multipartGraphQL(
            [
                'query' => <<<'GRAPHQL'
                    mutation CreateOwner($logo: Upload!, $documents: [Upload!]) {
                      createTestOwner(input: {
                        name: "Acme"
                        logo: $logo
                        files: { add: $documents }
                      }) {
                        id
                        logo { name extension }
                        files { name extension }
                      }
                    }
                    GRAPHQL,
                'variables' => [
                    'logo' => null,
                    'documents' => [null, null],
                ],
            ],
            [
                '0' => ['variables.logo'],
                '1' => ['variables.documents.0'],
                '2' => ['variables.documents.1'],
            ],
            [
                '0' => UploadedFile::fake()->image('logo.png'),
                '1' => UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf'),
                '2' => UploadedFile::fake()->create('brief.txt', 2, 'text/plain'),
            ],
        );

        $response->assertJsonMissingPath('errors')
            ->assertJsonPath('data.createTestOwner.logo.name', 'logo')
            ->assertJsonPath('data.createTestOwner.logo.extension', 'png')
            ->assertJsonCount(2, 'data.createTestOwner.files')
            ->assertJsonPath('data.createTestOwner.files.0.name', 'contract')
            ->assertJsonPath('data.createTestOwner.files.1.name', 'brief');

        $this->assertDatabaseCount('file_attachments', 3);
        $this->assertDatabaseHas('file_attachments', ['slot' => 'LOGO']);
        $this->assertDatabaseHas('file_attachments', ['collection' => 'DOCUMENTS']);
        $this->assertDatabaseHas('files', [
            'name' => 'contract',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'metadata' => '[]',
        ]);
        $this->assertDatabaseCount('files', 3);
        $this->assertCount(3, Storage::disk('files')->allFiles());

        $filtered = $this->graphQL(<<<'GRAPHQL'
            query FilterFiles($id: ID!) {
              testOwner(id: $id) {
                files(
                  where: { field: { name: { contains: "r" } } }
                  order: [{ field: { name: Asc } }]
                ) { name }
              }
            }
            GRAPHQL, [
            'id' => $response->json('data.createTestOwner.id'),
        ]);

        $filtered->assertJsonMissingPath('errors')
            ->assertJsonCount(2, 'data.testOwner.files')
            ->assertJsonPath('data.testOwner.files.0.name', 'brief')
            ->assertJsonPath('data.testOwner.files.1.name', 'contract');
    }

    public function test_graphql_can_clear_a_slot_and_only_remove_the_requested_unassigned_file(): void
    {
        $createResponse = $this->multipartGraphQL(
            [
                'query' => <<<'GRAPHQL'
                    mutation CreateOwner($logo: Upload!, $documents: [Upload!]) {
                      createTestOwner(input: {
                        name: "Acme"
                        logo: $logo
                        files: { add: $documents }
                      }) {
                        id
                        files { id }
                      }
                    }
                    GRAPHQL,
                'variables' => ['logo' => null, 'documents' => [null, null]],
            ],
            [
                '0' => ['variables.logo'],
                '1' => ['variables.documents.0'],
                '2' => ['variables.documents.1'],
            ],
            [
                '0' => UploadedFile::fake()->image('logo.png'),
                '1' => UploadedFile::fake()->create('keep.pdf'),
                '2' => UploadedFile::fake()->create('remove.pdf'),
            ],
        );
        $createResponse->assertJsonMissingPath('errors');

        $ownerId = $createResponse->json('data.createTestOwner.id');
        $fileIds = $createResponse->json('data.createTestOwner.files.*.id');

        $response = $this->graphQL(<<<'GRAPHQL'
            mutation UpdateOwner($id: ID!, $remove: [ID!]) {
              updateTestOwner(input: {
                id: $id
                logo: null
                files: { remove: $remove }
              }) {
                logo { id }
                files { id name }
              }
            }
            GRAPHQL, [
            'id' => $ownerId,
            'remove' => [$fileIds[1]],
        ]);

        $response->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateTestOwner.logo', null)
            ->assertJsonCount(1, 'data.updateTestOwner.files')
            ->assertJsonPath('data.updateTestOwner.files.0.id', $fileIds[0]);

        $this->assertDatabaseMissing('files', ['id' => $fileIds[1]]);
        $this->assertDatabaseHas('files', ['id' => $fileIds[0]]);
        $this->assertDatabaseCount('file_attachments', 1);
        $this->assertCount(1, Storage::disk('files')->allFiles());
    }
}
