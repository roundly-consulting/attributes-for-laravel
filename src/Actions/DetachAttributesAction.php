<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\RevisionData;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Events\AttributeDetached;
use RoundlyConsulting\Attributes\Models\Attribute;

final readonly class DetachAttributesAction
{
    public function __construct(
        private readonly RecordAttributeRevisionAction $recordRevision,
    ) {}

    /**
     * @param  Model&HasAttributes  $owner
     * @param  list<string>  $names
     */
    public function execute(Model $owner, array $names, bool $forceDelete = false): int
    {
        if ($names === []) {
            return 0;
        }

        $existing = $this->recordRevision->enabled()
            ? $owner->attachedAttributes()->whereIn('name', $names)->get()
            : null;

        $query = $owner->attachedAttributes()->whereIn('name', $names);

        $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

        $this->record($owner, $existing);

        foreach ($names as $name) {
            AttributeDetached::dispatch($owner, $name);
        }

        return (int) $deleted;
    }

    /**
     * @param  Model&HasAttributes  $owner
     * @param  Collection<int, Attribute>|null  $existing
     */
    private function record(Model $owner, ?Collection $existing): void
    {
        if ($existing === null) {
            return;
        }

        foreach ($existing as $attribute) {
            $raw = $attribute->getRawOriginal('value');

            $this->recordRevision->execute($owner, new RevisionData(
                name: $attribute->name,
                type: RevisionType::Detached,
                oldValue: $raw === null ? null : (string) $raw,
                newValue: null,
                oldValueType: $attribute->type(),
                newValueType: null,
                oldMeta: $attribute->meta,
                newMeta: null,
            ));
        }
    }
}
