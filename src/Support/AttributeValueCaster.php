<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Illuminate\Support\Facades\Crypt;
use RoundlyConsulting\Attributes\DataTransferObjects\StoredValue;
use RoundlyConsulting\Attributes\Enums\AttributeType;

final class AttributeValueCaster
{
    /**
     * Convert a typed value into its stored string + inferred type.
     *
     * When $encrypt is true and the value is non-null, the storage string
     * is wrapped with Crypt so the type round-trip is preserved on read.
     */
    public function toStorage(mixed $value, ?AttributeType $type = null, bool $encrypt = false): StoredValue
    {
        $type ??= AttributeType::forValue($value);

        if ($value === null) {
            return new StoredValue(null, $type, $encrypt);
        }

        $stored = $type->toStorage($value);

        if ($encrypt) {
            $stored = Crypt::encryptString($stored);
        }

        return new StoredValue($stored, $type, $encrypt);
    }

    /**
     * Convert a stored string back into its typed PHP value.
     */
    public function fromStorage(?string $stored, AttributeType $type, bool $encrypted = false): mixed
    {
        if ($stored === null) {
            return null;
        }

        if ($encrypted) {
            $stored = Crypt::decryptString($stored);
        }

        return $type->fromStorage($stored);
    }
}
