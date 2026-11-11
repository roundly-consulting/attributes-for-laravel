<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\ResolvesUniqueScope;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * The scope model a `unique => scope` value is unique within, as the string the
 * `unique_hash` covers. The resolver is the definition's `unique_scope`, else
 * `attributes.unique_scope`:
 *
 *  - a closure, given the owner;
 *  - a `ResolvesUniqueScope` class-string, resolved from the container;
 *  - otherwise an attribute or relation name on the owner (`team_id`, `team`). An
 *    unloaded relation is queried directly, so it works under preventLazyLoading.
 *
 * A model resolves to its morph class + key, an int/string to itself. Anything that
 * identifies no scope throws: the check fails closed.
 *
 * @internal used by UniqueIndex's callers (the value cast and the registry pre-check)
 */
final class UniqueScopeKey
{
    /**
     * @throws InvalidConfigurationException when no resolver is configured, or a class-string is not a ResolvesUniqueScope
     * @throws AttributesException when the owner resolves no scope
     */
    public static function for(AttributeDefinitionData $definition, Model $owner): string
    {
        $resolved = self::resolve($definition, $owner);

        if ($resolved instanceof Model) {
            $key = $resolved->exists ? $resolved->getKey() : null;

            if (is_int($key) || (is_string($key) && $key !== '')) {
                return self::encode([$resolved->getMorphClass(), (string) $key]);
            }
        } elseif (is_int($resolved) || (is_string($resolved) && $resolved !== '')) {
            return self::encode(['', (string) $resolved]);
        }

        throw AttributesException::unresolvedUniqueScope($definition->name, $resolved);
    }

    private static function resolve(AttributeDefinitionData $definition, Model $owner): mixed
    {
        $resolver = $definition->uniqueScope ?? AttributesConfig::uniqueScope() ?? throw new InvalidConfigurationException(sprintf(
            "Attribute [%s] is unique per scope but names no scope: set its definition's unique_scope or attributes.unique_scope.",
            $definition->name,
        ));

        if ($resolver instanceof Closure) {
            return $resolver($owner);
        }

        if (class_exists($resolver)) {
            if (! is_subclass_of($resolver, ResolvesUniqueScope::class)) {
                throw InvalidConfigurationException::notAnImplementation("{$definition->name}.unique_scope", ResolvesUniqueScope::class, $resolver);
            }

            /** @var ResolvesUniqueScope $instance */
            $instance = app($resolver);

            return $instance($owner);
        }

        if ($owner->isRelation($resolver) && ! $owner->relationLoaded($resolver)) {
            return $owner->{$resolver}()->getResults();
        }

        return $owner->getAttribute($resolver);
    }

    /**
     * Length-prefixed, so no key can forge a separator.
     *
     * @param  list<string>  $parts
     */
    private static function encode(array $parts): string
    {
        return implode('', array_map(static fn (string $part): string => strlen($part).':'.$part, $parts));
    }
}
