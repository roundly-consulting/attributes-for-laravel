<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Stringable;

/**
 * A typed wrapper around an already-resolved attribute value.
 *
 * Wraps a value the trait has already read (with defaults applied) and
 * exposes type-coerced accessors. No database access.
 */
final readonly class StoredAttributeValue implements Stringable
{
    public function __construct(private mixed $value) {}

    public function raw(): mixed
    {
        return $this->value;
    }

    public function int(): ?int
    {
        return $this->value === null ? null : (int) $this->scalar();
    }

    public function float(): ?float
    {
        return $this->value === null ? null : (float) $this->scalar();
    }

    /**
     * The value as a boolean. A string means what it says — `'false'`, `'off'`,
     * `'no'`, `'0'` and `''` are false — parsed as the write path and the query
     * scopes parse it; any other string is true.
     */
    public function bool(): ?bool
    {
        if (is_string($this->value)) {
            return filter_var($this->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->value !== '';
        }

        return $this->value === null ? null : (bool) $this->value;
    }

    public function string(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if ($this->value instanceof DateTimeInterface) {
            return Carbon::instance($this->value)->toIso8601String();
        }

        if (is_array($this->value)) {
            return (string) json_encode($this->value);
        }

        return (string) $this->scalar();
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function array(): ?array
    {
        if ($this->value === null) {
            return null;
        }

        return is_array($this->value) ? $this->value : [$this->value];
    }

    /**
     * The value as a Carbon — null when unset or when it is not a date at all (an
     * array, a boolean, a blank or unparsable string), never "now".
     */
    public function date(): ?Carbon
    {
        if ($this->value instanceof DateTimeInterface) {
            return Carbon::instance($this->value);
        }

        // Carbon parses '' (and so false) as "now"; a boolean is no date either.
        if (! is_scalar($this->value) || is_bool($this->value) || trim((string) $this->value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $this->value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    public function isNull(): bool
    {
        return $this->value === null;
    }

    public function __toString(): string
    {
        return $this->string() ?? '';
    }

    private function scalar(): string|int|float|bool
    {
        return is_scalar($this->value) ? $this->value : '';
    }
}
