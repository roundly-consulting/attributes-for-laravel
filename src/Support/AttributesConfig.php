<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict reads of the host's non-boolean attributes settings. A key that is not set
 * (absent, null or blank — a host's `KEY=`) takes the default; any other invalid value
 * throws {@see InvalidConfigurationException} naming the key, so a typo never silently
 * becomes a different setting.
 *
 * @internal
 */
final class AttributesConfig
{
    /**
     * How many days a trashed attribute is kept before `attributes:prune` removes it. `0`
     * is allowed and purges every trashed attribute; blank is not set and gives 30; junk
     * (`thirty`, `7.5`) and negatives throw.
     */
    public static function pruneAfterDays(): int
    {
        return Config::integer('attributes.prune_after_days', 30, min: 0);
    }

    /**
     * Whether permanently deleting an owner removes its attribute rows (default on).
     */
    public static function deleteWithOwner(): bool
    {
        return Config::boolean('attributes.delete_with_owner', true);
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
     * A string setting the caller read: the default when not set (null, `''` or
     * whitespace), otherwise it must be a string.
     */
    public static function string(string $key, mixed $value, string $default): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $default;
        }

        if (! is_string($value)) {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }
}
