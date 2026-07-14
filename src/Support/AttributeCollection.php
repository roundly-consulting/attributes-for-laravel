<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use RoundlyConsulting\Attributes\Models\Attribute;

/**
 * @template TAttribute of Attribute
 *
 * @extends Collection<int, TAttribute>
 */
final class AttributeCollection extends Collection
{
    /**
     * Flatten to a name => value map. Last write wins on duplicate names.
     *
     * @return array<string, mixed>
     */
    public function toKeyValue(): array
    {
        $map = [];

        foreach ($this as $attribute) {
            $map[$attribute->name] = $attribute->value;
        }

        return $map;
    }

    /**
     * Re-key the collection by attribute name.
     *
     * @return BaseCollection<string, Attribute>
     */
    public function keyByName(): BaseCollection
    {
        $keyed = new BaseCollection;

        foreach ($this as $attribute) {
            $keyed->put($attribute->name, $attribute);
        }

        return $keyed;
    }
}
