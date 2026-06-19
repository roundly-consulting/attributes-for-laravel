<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\DataTransferObjects\RevisionData;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Events\AttributeAttached;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

final class AttachAttributeAction
{
    public function __construct(
        private readonly AttributeRegistry $registry,
        private readonly RecordAttributeRevisionAction $recordRevision,
    ) {}

    /**
     * @param  Model&HasAttributes  $owner
     */
    public function execute(Model $owner, AttributeData $data): Attribute
    {
        $this->registry->assertKnown($data->name);
        $this->registry->validateFor($owner, $data->name, $data->value);
        $this->registry->assertUnique($owner, $data->name, $data->value);

        $existing = $owner->attachedAttributes()->where('name', $data->name)->first();

        $existedBefore = $existing !== null;
        $oldRaw = $existing !== null ? $this->rawValue($existing) : null;
        $oldType = $existing?->type();
        $oldMeta = $existing?->meta;

        $attribute = $existing ?? $owner->attachedAttributes()->make(['name' => $data->name]);

        // Load the owner relation before the value cast runs so per-model
        // definitions (encryption, etc.) resolve regardless of attribute order.
        $attribute->setRelation('owner', $owner);
        $attribute->forceFill(['value' => $data->value, 'meta' => $data->meta]);
        $attribute->save();

        if ($this->recordRevision->enabled()) {
            $attribute->refresh();

            $this->recordRevision->execute($owner, new RevisionData(
                name: $attribute->name,
                type: $existedBefore ? RevisionType::Updated : RevisionType::Attached,
                oldValue: $oldRaw,
                newValue: $this->rawValue($attribute),
                oldValueType: $oldType,
                newValueType: $attribute->type(),
                oldMeta: $oldMeta,
                newMeta: $attribute->meta,
            ));
        }

        AttributeAttached::dispatch($owner, $attribute);

        return $attribute;
    }

    private function rawValue(Attribute $attribute): ?string
    {
        $raw = $attribute->getRawOriginal('value');

        return $raw === null ? null : (string) $raw;
    }
}
