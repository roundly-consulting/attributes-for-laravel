<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Illuminate\Support\Facades\Crypt;
use RoundlyConsulting\Attributes\DataTransferObjects\StoredValue;
use RoundlyConsulting\Attributes\Enums\AttributeType;

final class AttributeValueCaster
{
    /**
     * Convert a typed value into its stored string + type — the given type when
     * there is one (a definition's), otherwise the one inferred from the value.
     *
     * When $encrypt is true and the value is non-null, the storage string
     * is wrapped with Crypt so the type round-trip is preserved on read.
     */
    public function toStorage(mixed $value, ?AttributeType $type = null, bool $encrypt = false): StoredValue
    {
        return $this->seal($this->plain($value, $type), $encrypt);
    }

    /**
     * The unencrypted storage form of a value — what equality, ordering and the
     * unique index are computed from.
     */
    public function plain(mixed $value, ?AttributeType $type = null): StoredValue
    {
        $type ??= AttributeType::forValue($value);

        return new StoredValue($value === null ? null : $type->toStorage($value), $type);
    }

    /**
     * Encrypt a plain storage form (a no-op for `null`, which is never run
     * through Crypt).
     */
    public function seal(StoredValue $plain, bool $encrypt): StoredValue
    {
        if (! $encrypt || $plain->value === null) {
            return new StoredValue($plain->value, $plain->type, $encrypt);
        }

        return new StoredValue(Crypt::encryptString($plain->value), $plain->type, true);
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
