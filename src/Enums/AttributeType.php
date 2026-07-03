<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Enums;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Enums\Helpers;

enum AttributeType: string
{
    use Helpers;

    case String_ = 'string';
    case Integer = 'integer';
    case Float_ = 'float';
    case Boolean = 'boolean';
    case Array_ = 'array';
    case DateTime = 'datetime';

    /**
     * Infer the storage type from a native PHP value.
     */
    public static function forValue(mixed $value): self
    {
        return match (true) {
            is_bool($value) => self::Boolean,
            is_int($value) => self::Integer,
            is_float($value) => self::Float_,
            is_array($value) => self::Array_,
            $value instanceof DateTimeInterface => self::DateTime,
            default => self::String_,
        };
    }

    /**
     * Convert a typed value into its string column form.
     */
    public function toStorage(mixed $value): string
    {
        return match ($this) {
            self::Boolean => $this->encodeBoolean($value),
            self::Integer => (string) $this->requireScalarInt($value),
            self::Float_ => $this->encodeFloat($value),
            self::Array_ => $this->encodeArray($value),
            self::DateTime => $this->encodeDateTime($value),
            self::String_ => $this->encodeString($value),
        };
    }

    /**
     * Convert a stored string back into its typed PHP value.
     */
    public function fromStorage(?string $stored): mixed
    {
        if ($stored === null) {
            return null;
        }

        return match ($this) {
            self::Boolean => $stored === '1',
            self::Integer => (int) $stored,
            self::Float_ => (float) $stored,
            self::Array_ => $this->decodeArray($stored),
            self::DateTime => Carbon::parse($stored)->toImmutable(),
            self::String_ => $stored,
        };
    }

    /**
     * The Laravel validation rule that enforces this type on a value.
     *
     * Intentionally shadows the {@see Helpers::validationRule()} static
     * membership rule (`in:...`): here it returns the per-type value rule for a
     * given case. Hosts needing the membership rule use
     * `'in:'.AttributeType::values()->implode(',')`.
     */
    public function validationRule(): string
    {
        return match ($this) {
            self::String_ => 'string',
            self::Integer => 'integer',
            self::Float_ => 'numeric',
            self::Boolean => 'boolean',
            self::Array_ => 'array',
            self::DateTime => 'date',
        };
    }

    private function encodeBoolean(mixed $value): string
    {
        return $value ? '1' : '0';
    }

    private function requireScalarInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $this->isIntegerString($value)) {
            return (int) $value;
        }

        throw InvalidAttributeValueException::forName(
            $this->value,
            'expected an integer value',
        );
    }

    private function encodeFloat(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (string) (float) $value;
        }

        throw InvalidAttributeValueException::forName(
            $this->value,
            'expected a numeric value',
        );
    }

    private function encodeArray(mixed $value): string
    {
        if (! is_array($value)) {
            throw InvalidAttributeValueException::forName(
                $this->value,
                'expected an array value',
            );
        }

        $encoded = json_encode($value);

        if ($encoded === false) {
            throw InvalidAttributeValueException::forName(
                $this->value,
                'value could not be encoded to JSON',
            );
        }

        return $encoded;
    }

    private function encodeDateTime(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        if (is_string($value)) {
            return Carbon::parse($value)->toIso8601String();
        }

        throw InvalidAttributeValueException::forName(
            $this->value,
            'expected a date value',
        );
    }

    private function encodeString(mixed $value): string
    {
        if (is_array($value)) {
            throw InvalidAttributeValueException::forName(
                $this->value,
                'cannot store an array as a string',
            );
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        return (string) $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeArray(string $stored): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode($stored, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function isIntegerString(string $value): bool
    {
        return (string) (int) $value === $value;
    }
}
