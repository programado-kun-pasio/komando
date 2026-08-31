<?php declare(strict_types=1);

namespace Programado\Komando\Files\GraphQL\Directives;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Programado\Komando\Files\Contracts\HasFileAttachmentsContract;
use Programado\Komando\Files\Services\FileAttachmentService;

final class FileAttachmentsDirective extends BaseDirective implements ArgResolver, FieldResolver
{
    public function __construct(
        private readonly FileAttachmentService $fileAttachmentService,
    ) {}

    public static function definition(): string
    {
        $slotType = strval(config('komando.files.graphql_slot_type', 'String'));

        if (preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/', $slotType) !== 1) {
            throw new DefinitionException('komando.files.graphql_slot_type must be a valid GraphQL type name.');
        }

        return <<<GRAPHQL
"""
Read or mutate multiple files in a named attachment slot on a model implementing HasFileAttachmentsContract.
"""
directive @fileAttachments(slot: {$slotType}!) on FIELD_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function __invoke(mixed $root, mixed $value): void
    {
        $owner = $this->owner($root);
        $slot = $this->slot();

        if (! $value instanceof ArgumentSet) {
            throw new DefinitionException('@fileAttachments expects a FileAttachmentChangesInput value.');
        }

        $changes = $value->toArray();

        collect($changes['add'] ?? [])
            ->each(function (mixed $upload) use ($owner, $slot): void {
                if (! $upload instanceof UploadedFile) {
                    throw new DefinitionException('@fileAttachments.add expects uploaded files.');
                }

                $this->fileAttachmentService->add($owner, $upload, $slot);
            });

        $this->fileAttachmentService->remove(
            $owner,
            Collection::make($changes['remove'] ?? [])
                ->map(static fn (mixed $id): int|string => is_int($id) ? $id : strval($id)),
            $slot,
        );
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        $slot = $this->slot();

        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($slot): Collection {
            $query = $this->owner($root)->files();

            $query->wherePivot('collection', $slot instanceof BackedEnum ? $slot->value : $slot);

            return $resolveInfo
                ->enhanceBuilder($query, [], $root, $args, $context, $resolveInfo)
                ->get();
        };
    }

    private function slot(): BackedEnum|string
    {
        $slot = $this->directiveArgValue('slot');

        if (! is_string($slot) && ! $slot instanceof BackedEnum) {
            throw new DefinitionException('@fileAttachments.slot must resolve to a string or backed enum.');
        }

        return $slot;
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
