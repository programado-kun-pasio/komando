<?php declare(strict_types=1);

namespace Programado\Komando\Files\GraphQL\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Programado\Komando\Files\Contracts\HasFileAttachmentsContract;
use Programado\Komando\Files\Services\FileAttachmentService;

final class FileAttachmentsDirective extends BaseDirective implements ArgResolver, FieldResolver
{
    public function __construct(
        private readonly FileAttachmentService $fileAttachmentService,
    ) {}

    public static function definition(): string
    {
        return <<<'GRAPHQL'
"""
Read or mutate unassigned file attachments on a model implementing HasFileAttachmentsContract.
"""
directive @fileAttachments on FIELD_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function __invoke(mixed $root, mixed $value): void
    {
        $owner = $this->owner($root);

        if (! $value instanceof ArgumentSet) {
            throw new DefinitionException('@fileAttachments expects a FileAttachmentChangesInput value.');
        }

        $changes = $value->toArray();

        collect($changes['add'] ?? [])
            ->each(function (mixed $upload) use ($owner): void {
                if (! $upload instanceof UploadedFile) {
                    throw new DefinitionException('@fileAttachments.add expects uploaded files.');
                }

                $this->fileAttachmentService->add($owner, $upload);
            });

        $this->fileAttachmentService->remove(
            $owner,
            Collection::make($changes['remove'] ?? [])
                ->map(static fn (mixed $id): int|string => is_int($id) ? $id : strval($id)),
        );
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        return fn (mixed $root) => $this->owner($root)
            ->files()
            ->wherePivotNull('slot')
            ->get();
    }

    /** @return Model&HasFileAttachmentsContract */
    private function owner(mixed $root): Model
    {
        if (! $root instanceof Model || ! $root instanceof HasFileAttachmentsContract) {
            throw new DefinitionException('@fileAttachments may only be used on models implementing HasFileAttachmentsContract.');
        }

        return $root;
    }
}
