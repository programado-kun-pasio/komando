<?php declare(strict_types=1);

namespace Programado\Komando\Files\GraphQL\Directives;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Programado\Komando\Files\Contracts\HasFileAttachmentsContract;
use Programado\Komando\Files\Services\FileAttachmentService;

final class FileSlotDirective extends BaseDirective implements ArgResolver, FieldResolver
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
Read or mutate a named file slot on a model implementing HasFileAttachmentsContract.
"""
directive @fileSlot(slot: {$slotType}!) on FIELD_DEFINITION | INPUT_FIELD_DEFINITION
GRAPHQL;
    }

    public function __invoke(mixed $root, mixed $value): void
    {
        $owner = $this->owner($root);
        $slot = $this->slot();

        if ($value === null) {
            $this->fileAttachmentService->removeSlot($owner, $slot);

            return;
        }

        if (! $value instanceof UploadedFile) {
            throw new DefinitionException("@fileSlot expects an uploaded file or null on '{$this->nodeName()}'.");
        }

        $this->fileAttachmentService->replace($owner, $slot, $value);
    }

    public function resolveField(FieldValue $fieldValue): callable
    {
        $slot = $this->slot();

        return fn (mixed $root) => $this->owner($root)->fileForSlot($slot);
    }

    /** @return Model&HasFileAttachmentsContract */
    private function owner(mixed $root): Model
    {
        if (! $root instanceof Model || ! $root instanceof HasFileAttachmentsContract) {
            throw new DefinitionException('@fileSlot may only be used on models implementing HasFileAttachmentsContract.');
        }

        return $root;
    }

    private function slot(): BackedEnum|string
    {
        $slot = $this->directiveArgValue('slot');

        if (! is_string($slot) && ! $slot instanceof BackedEnum) {
            throw new DefinitionException('@fileSlot.slot must resolve to a string or backed enum.');
        }

        return $slot;
    }
}
