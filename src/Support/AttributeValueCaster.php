<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use RoundlyConsulting\Attributes\DataTransferObjects\StoredValue;
use RoundlyConsulting\Attributes\Enums\AttributeType;

final class AttributeValueCaster
{
    /**
     * Convert a typed value into its stored string + inferred type.
     */
    public function toStorage(mixed $value, ?AttributeType $type = null): StoredValue
    {
        $type ??= AttributeType::forValue($value);

        if ($value === null) {
            return new StoredValue(null, $type);
        }

        return new StoredValue($type->toStorage($value), $type);
    }

    /**
     * Convert a stored string back into its typed PHP value.
     */
    public function fromStorage(?string $stored, AttributeType $type): mixed
    {
        return $type->fromStorage($stored);
    }
}
