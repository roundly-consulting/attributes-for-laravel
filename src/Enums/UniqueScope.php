<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Enums;

use RoundlyConsulting\Enums\Helpers;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

enum UniqueScope: string
{
    use Helpers;

    case None = 'none';
    case Owner = 'owner';
    case Global_ = 'global';

    /**
     * Resolve a uniqueness scope from a config value.
     *
     * Accepts the backed strings ('owner', 'global', 'none'), booleans
     * (true => owner-scoped, false => none), or null (none). Anything else — a typo
     * such as 'globl' — throws instead of silently switching uniqueness off.
     *
     * @param  string  $key  the setting being read, named in the error
     *
     * @throws InvalidConfigurationException
     */
    public static function fromConfig(mixed $value, string $key = 'unique'): self
    {
        if (is_bool($value)) {
            return $value ? self::Owner : self::None;
        }

        return Config::for([$key => $value])->enum($key, self::class, self::None);
    }

    public function enforces(): bool
    {
        return $this !== self::None;
    }
}
