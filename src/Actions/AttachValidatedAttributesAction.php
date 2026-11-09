<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Models\Attribute;

final readonly class AttachValidatedAttributesAction
{
    public function __construct(private WriteAttributeSetAction $writeSet) {}

    /**
     * Attach (or update) several attributes validated as one set, all or nothing:
     * the owner's stored values overlaid by these are validated in a single pass, so
     * a definition's rules may refer to other attributes (`required_if:type,physical`,
     * `gte:min_price`). Runs with the owner's row locked, so concurrent whole-set
     * writes of one owner validate one after another.
     *
     * @param  Model&HasAttributes  $owner
     * @return Collection<int, Attribute>
     */
    public function execute(Model $owner, AttributeData ...$data): Collection
    {
        return $this->writeSet->execute($owner, array_values($data));
    }
}
