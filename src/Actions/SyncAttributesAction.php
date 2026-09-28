<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Events\AttributesSynced;
use RoundlyConsulting\Attributes\Models\Attribute;

final readonly class SyncAttributesAction
{
    public function __construct(
        private AttachAttributeAction $attachAttribute,
        private DetachAttributesExceptAction $detachAttributesExcept,
    ) {}

    /**
     * Make the owner's attributes exactly the given set. Removed attributes go
     * through DetachAttributesExceptAction, so they fire AttributeDetached and
     * record a detached revision like any other detach.
     *
     * @param  Model&HasAttributes  $owner
     * @param  list<AttributeData>  $data
     * @return Collection<int, Attribute>
     */
    public function execute(Model $owner, array $data, bool $forceDelete = false): Collection
    {
        $this->detachAttributesExcept->execute(
            $owner,
            array_map(static fn (AttributeData $item): string => $item->name, $data),
            $forceDelete,
        );

        /** @var Collection<int, Attribute> $attributes */
        $attributes = new Collection;

        foreach ($data as $item) {
            $attributes->push($this->attachAttribute->execute($owner, $item));
        }

        AttributesSynced::dispatch($owner, $data);

        return $attributes;
    }
}
