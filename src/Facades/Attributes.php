<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Attributes\AttributesManager;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\OwnerAttributes;
use RoundlyConsulting\Attributes\Testing\AttributesFake;

/**
 * @method static OwnerAttributes for(Model $owner)
 * @method static int prune(?int $days = null)
 * @method static AttributesManager define(AttributeDefinitionData $definition)
 * @method static AttributesManager defineMany(AttributeDefinitionData ...$definitions)
 * @method static bool has(string $name)
 * @method static AttributeDefinitionData|null get(string $name)
 * @method static array<string, AttributeDefinitionData> all()
 * @method static AttributesManager forget(string $name)
 * @method static AttributesManager flush()
 * @method static AttributeDefinitionData|null resolveFor(?Model $owner, string $name)
 * @method static array<string, AttributeDefinitionData> definitionsFor(Model $owner)
 * @method static mixed default(string $name, ?Model $owner = null)
 * @method static list<string> requiredNames()
 * @method static void validate(string $name, mixed $value)
 * @method static void validateFor(?Model $owner, string $name, mixed $value)
 * @method static void assertUnique(Model $owner, string $name, mixed $value)
 * @method static bool isStrict()
 * @method static void assertKnown(string $name, ?Model $owner = null)
 *
 * @see AttributesManager
 */
final class Attributes extends Facade
{
    /**
     * Swap the manager for a recording fake (pass-through: real reads and
     * writes still run) and return it for assertions. The container binding is
     * swapped too, so injected managers and `HasAttributes` trait writes record
     * as well. Definitions keep using the real registry.
     */
    public static function fake(): AttributesFake
    {
        $fake = self::getFacadeApplication()->make(AttributesFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AttributesManager::class;
    }
}
