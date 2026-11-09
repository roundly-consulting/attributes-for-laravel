<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Events\AttributesSynced;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Support\OwnerTransaction;

final readonly class SyncAttributesAction
{
    public function __construct(
        private AttributeRegistry $registry,
        private WriteAttributeAction $write,
        private DetachAttributesExceptAction $detachAttributesExcept,
        private WriteAttributeSetAction $writeSet,
    ) {}

    /**
     * Make the owner's attributes exactly the given set, all or nothing: every
     * value is validated before anything is detached or written, and the detach
     * and the writes share one transaction. Removed attributes go through
     * DetachAttributesExceptAction, so they fire AttributeDetached and record a
     * detached revision like any other detach. With `attributes.validation.whole_set`
     * on, the given set is validated as one (rules may refer to each other), with the
     * owner's row locked.
     *
     * @param  Model&HasAttributes  $owner
     * @param  list<AttributeData>  $data
     * @return Collection<int, Attribute>
     */
    public function execute(Model $owner, array $data, bool $forceDelete = false): Collection
    {
        $names = array_map(static fn (AttributeData $item): string => $item->name, $data);

        if ($this->registry->validatesWholeSet()) {
            $attributes = $this->writeSet->execute(
                $owner,
                $data,
                replace: true,
                before: function () use ($owner, $names, $forceDelete): void {
                    $this->detachAttributesExcept->execute($owner, $names, $forceDelete);
                },
            );

            AttributesSynced::dispatch($owner, $data);

            return $attributes;
        }

        foreach ($data as $item) {
            $this->registry->assertWritable($owner, $item->name, $item->value);
        }

        return OwnerTransaction::run($owner, function () use ($owner, $data, $names, $forceDelete): Collection {
            $this->detachAttributesExcept->execute($owner, $names, $forceDelete);

            /** @var Collection<int, Attribute> $attributes */
            $attributes = new Collection;

            foreach ($data as $item) {
                $attributes->push($this->write->execute($owner, $item));
            }

            AttributesSynced::dispatch($owner, $data);

            return $attributes;
        });
    }
}
