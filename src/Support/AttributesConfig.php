<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;
use RoundlyConsulting\Attributes\Http\Resources\RevisionActorResource;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
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
     * The key type of the revisions' `actor` morph column (default bigint).
     */
    public static function actorKeyType(): KeyType
    {
        return KeyType::fromConfig('attributes.actor_key_type');
    }

    /**
     * Whether revisions record who made the change (needs the actor columns).
     */
    public static function recordsActor(): bool
    {
        return Config::boolean('attributes.history.actor.enabled');
    }

    /**
     * The auth guard the default actor comes from; null = the default guard.
     */
    public static function actorGuard(): ?string
    {
        $guard = config('attributes.history.actor.guard');

        if ($guard === null || (is_string($guard) && trim($guard) === '')) {
            return null;
        }

        return self::string('attributes.history.actor.guard', $guard, '');
    }

    /**
     * Whether every value write validates the owner's whole attribute set.
     */
    public static function validatesWholeSet(): bool
    {
        return Config::boolean('attributes.validation.whole_set');
    }

    /**
     * The resource a revision's actor renders through: blank or null = the package's
     * `{id, type}` default; anything that is not an instantiable JsonResource subclass
     * (the bare base included — it would dump every visible column) throws.
     *
     * @return class-string<JsonResource>
     */
    public static function revisionActorResource(): string
    {
        $resource = config('attributes.resources.revision_actor');

        if ($resource === null || (is_string($resource) && trim($resource) === '')) {
            return RevisionActorResource::class;
        }

        if (! is_string($resource)
            || ! is_subclass_of($resource, JsonResource::class)
            || ! new ReflectionClass($resource)->isInstantiable()) {
            throw InvalidConfigurationException::notAnImplementation('attributes.resources.revision_actor', JsonResource::class, $resource);
        }

        return $resource;
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
