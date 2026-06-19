<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Events\AttributesSynced;

final class SyncAttributesAction
{
    public function __construct(
        private readonly AttachAttributeAction $attachAttribute,
    ) {}

    /**
     * @param  Model&HasAttributes  $owner
     * @param  list<AttributeData>  $data
     * @return list<AttributeData>
     */
    public function execute(Model $owner, array $data, bool $forceDelete = false): array
    {
        $names = array_map(static fn (AttributeData $item): string => $item->name, $data);

        $remove = $owner->attachedAttributes()->whereNotIn('name', $names);

        $forceDelete ? $remove->forceDelete() : $remove->delete();

        foreach ($data as $item) {
            $this->attachAttribute->execute($owner, $item);
        }

        AttributesSynced::dispatch($owner, $data);

        return $data;
    }
}
