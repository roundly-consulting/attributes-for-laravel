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
use RoundlyConsulting\Attributes\Support\OwnerTransaction;

final readonly class DetachAttributesAction
{
    public function __construct(
        private RecordAttributeRevisionAction $recordRevision,
    ) {}

    /**
     * Detach the named attributes (soft delete; `$forceDelete` deletes for good,
     * already soft-deleted rows of those names included). Only attributes that
     * were attached fire AttributeDetached and record a detached revision — a
     * name that was never attached is a no-op. A soft-deleted value gives up its
     * unique slot. Returns how many rows were removed.
     *
     * @param  Model&HasAttributes  $owner
     * @param  list<string>  $names
     */
    public function execute(Model $owner, array $names, bool $forceDelete = false): int
    {
        if ($names === []) {
            return 0;
        }

        return OwnerTransaction::run($owner, function () use ($owner, $names, $forceDelete): int {
            $attached = $owner->attachedAttributes()->whereIn('name', $names)->lockForUpdate()->get();

            if ($forceDelete) {
                // Purges already soft-deleted rows of these names as well.
                $deleted = $owner->attachedAttributes()->withTrashed()->whereIn('name', $names)->forceDelete();
            } elseif ($attached->isEmpty()) {
                return 0;
            } else {
                $query = $owner->attachedAttributes()->whereKey($attached->modelKeys());
                // A soft-deleted value gives up its unique slot.
                (clone $query)->update(['unique_hash' => null]);
                $deleted = $query->delete();
            }

            $this->record($owner, $attached);

            $detached = $attached->pluck('name')->all();

            foreach (array_unique($names) as $name) {
                if (in_array($name, $detached, true)) {
                    AttributeDetached::dispatch($owner, $name);
                }
            }

            return (int) $deleted;
        });
    }

    /**
     * @param  Model&HasAttributes  $owner
     * @param  Collection<int, Attribute>  $attached
     */
    private function record(Model $owner, Collection $attached): void
    {
        if (! $this->recordRevision->enabled()) {
            return;
        }

        foreach ($attached as $attribute) {
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
