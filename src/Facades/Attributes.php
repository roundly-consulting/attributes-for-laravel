<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

/**
 * @method static AttributeRegistry define(\RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData $definition)
 * @method static AttributeRegistry defineMany(\RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData ...$definitions)
 * @method static bool has(string $name)
 * @method static \RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData|null get(string $name)
 * @method static array<string, \RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData> all()
 * @method static AttributeRegistry forget(string $name)
 * @method static AttributeRegistry flush()
 * @method static void validate(string $name, mixed $value)
 * @method static bool isStrict()
 * @method static void assertKnown(string $name)
 *
 * @see AttributeRegistry
 */
final class Attributes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AttributeRegistry::class;
    }
}
