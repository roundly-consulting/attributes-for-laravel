<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict reads of the host's non-boolean attributes settings. An absent (null) key takes
 * the default; a present but invalid value throws {@see InvalidConfigurationException}
 * naming the key, so a typo never silently becomes a different setting.
 *
 * @internal
 */
final class AttributesConfig
{
    /**
     * How many days a trashed attribute is kept before `attributes:prune` removes it. `0`
     * is allowed and purges every trashed attribute; junk (`thirty`, `7.5`, `''`) and
     * negatives throw — they used to fall back to 30.
     */
    public static function pruneAfterDays(): int
    {
        return Config::integer('attributes.prune_after_days', 30, min: 0);
    }

    public static function table(): string
    {
        return self::string('attributes.table', config('attributes.table'), 'attributes');
    }

    public static function historyTable(): string
    {
        return self::string('attributes.history.table', config('attributes.history.table'), 'attribute_revisions');
    }

    /**
     * A string setting the caller read: the default when null, otherwise a non-empty string.
     */
    public static function string(string $key, mixed $value, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        if (! is_string($value) || trim($value) === '') {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }
}
