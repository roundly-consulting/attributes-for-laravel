<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Enums;

enum UniqueScope: string
{
    case None = 'none';
    case Owner = 'owner';
    case Global_ = 'global';

    /**
     * Resolve a uniqueness scope from a config value.
     *
     * Accepts the backed strings ('owner', 'global', 'none'), booleans
     * (true => owner-scoped, false => none), or null.
     */
    public static function fromConfig(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? self::Owner : self::None;
        }

        if (is_string($value)) {
            return self::tryFrom($value) ?? self::None;
        }

        return self::None;
    }

    public function enforces(): bool
    {
        return $this !== self::None;
    }
}
