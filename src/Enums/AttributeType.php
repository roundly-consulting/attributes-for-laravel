<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Enums;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
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
     * Whether values of this type compare and sort as numbers (integer and float
     * form one numeric family: `10` and `10.0` are the same value).
     */
    public function isNumeric(): bool
    {
        return $this === self::Integer || $this === self::Float_;
    }

    /**
     * Convert a typed value into its string column form.
     *
     * Datetimes are normalized to UTC so the stored strings of one instant are
     * identical and sort chronologically; floats keep the shortest form that
     * reads back as the very same float.
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
            // Stored in UTC; handed back as the same instant in the app timezone.
            self::DateTime => CarbonImmutable::parse($stored)->setTimezone(date_default_timezone_get()),
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
        // A request string ('0', 'false', 'off') means what it says, not PHP truthiness.
        if (is_string($value)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value !== '';
        }

        return $value ? '1' : '0';
    }

    private function requireScalarInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        // Laravel's `integer` rule accepts an integral float (5.0) — so must the store.
        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) <= PHP_INT_MAX) {
            return (int) $value;
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
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $float = (float) $value;

            if (is_finite($float)) {
                return $this->shortestFloat($float);
            }
        }

        throw InvalidAttributeValueException::forName(
            $this->value,
            'expected a finite numeric value',
        );
    }

    /**
     * The shortest decimal form that reads back as exactly `$value`.
     *
     * `(string) $float` keeps only `precision` (14) significant digits and so
     * silently rounds; 17 always round-trip. `%H` is `%G` with a locale-independent
     * decimal point.
     */
    private function shortestFloat(float $value): string
    {
        foreach ([15, 16] as $precision) {
            $candidate = sprintf('%.'.$precision.'H', $value);

            if ((float) $candidate === $value) {
                return $candidate;
            }
        }

        return sprintf('%.17H', $value);
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
            return CarbonImmutable::instance($value)->utc()->toIso8601String();
        }

        if (is_string($value)) {
            try {
                return CarbonImmutable::parse($value)->utc()->toIso8601String();
            } catch (InvalidFormatException) {
                // Reported below as the package's own exception.
            }
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
