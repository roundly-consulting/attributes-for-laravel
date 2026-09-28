<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Models\Attribute;

final readonly class AttachAttributesAction
{
    public function __construct(
        private readonly AttachAttributeAction $attachAttribute,
    ) {}

    /**
     * @param  Model&HasAttributes  $owner
     * @return Collection<int, Attribute>
     */
    public function execute(Model $owner, AttributeData ...$data): Collection
    {
        /** @var Collection<int, Attribute> $attributes */
        $attributes = new Collection;

        foreach ($data as $attribute) {
            $attributes->push($this->attachAttribute->execute($owner, $attribute));
        }

        return $attributes;
    }
}
