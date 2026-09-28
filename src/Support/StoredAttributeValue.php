<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

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

    public function bool(): ?bool
    {
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
     * array, say), never "now".
     */
    public function date(): ?Carbon
    {
        if ($this->value instanceof DateTimeInterface) {
            return Carbon::instance($this->value);
        }

        if (! is_scalar($this->value)) {
            return null;
        }

        return Carbon::parse((string) $this->value);
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
