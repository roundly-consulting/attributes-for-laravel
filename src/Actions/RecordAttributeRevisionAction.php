<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\DataTransferObjects\RevisionData;
use RoundlyConsulting\Attributes\Models\AttributeRevision;

/**
 * @internal building block of the attach/detach actions — history is written
 * as a side effect of a write, never on its own.
 */
final readonly class RecordAttributeRevisionAction
{
    /**
     * Persist a revision for an owner's attribute change.
     *
     * A cheap no-op when history is disabled in config.
     */
    public function execute(Model $owner, RevisionData $data): ?AttributeRevision
    {
        if (! $this->enabled()) {
            return null;
        }

        return AttributeRevision::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'name' => $data->name,
            'type' => $data->type,
            'old_value' => $data->oldValue,
            'new_value' => $data->newValue,
            'old_value_type' => $data->oldValueType?->value,
            'new_value_type' => $data->newValueType?->value,
            'old_meta' => $data->oldMeta,
            'new_meta' => $data->newMeta,
        ]);
    }

    public function enabled(): bool
    {
        return (bool) config('attributes.history.enabled', false);
    }
}
