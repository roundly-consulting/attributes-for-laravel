<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Registry;

use Closure;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Parses a raw definition array (from config or a model) into a DTO.
 *
 * Shared by the service provider (config path) and the registry
 * (per-model path) so both honour exactly the same schema keys.
 *
 * Every key is read strictly: an absent (null) key takes its default, and anything else
 * must be valid or the parse throws {@see InvalidConfigurationException} naming
 * `{source}.{name}.{key}`. A typo'd `type` no longer stores the value as a string, a
 * typo'd `unique` no longer switches uniqueness off, and a non-array `rules` is no
 * longer dropped.
 */
final class DefinitionFactory
{
    /**
     * Parse one entry of a definitions map, which must itself be an array.
     *
     * @param  string  $source  where the map came from, for the error message
     *
     * @throws InvalidConfigurationException
     */
    public static function fromRaw(string $name, mixed $definition, string $source = 'attributes.definitions'): AttributeDefinitionData
    {
        if (! is_array($definition)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration value [%s.%s] must be a definition array, [%s] given.',
                $source,
                $name,
                is_scalar($definition) ? var_export($definition, true) : get_debug_type($definition),
            ));
        }

        /** @var array<string, mixed> $definition */
        return self::fromArray($name, $definition, $source);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  string  $source  where the definition came from, for the error message
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $name, array $definition, string $source = 'attributes.definitions'): AttributeDefinitionData
    {
        $key = "{$source}.{$name}";
        $read = Config::for([
            "{$key}.type" => $definition['type'] ?? null,
            "{$key}.required" => $definition['required'] ?? null,
            "{$key}.encrypted" => $definition['encrypted'] ?? null,
        ]);

        $unique = UniqueScope::fromConfig($definition['unique'] ?? null, "{$key}.unique");

        return new AttributeDefinitionData(
            name: $name,
            type: $read->enum("{$key}.type", AttributeType::class, AttributeType::String_),
            rules: self::rules("{$key}.rules", $definition['rules'] ?? null),
            default: $definition['default'] ?? null,
            required: $read->boolean("{$key}.required"),
            unique: $unique,
            encrypted: $read->boolean("{$key}.encrypted"),
            uniqueScope: self::uniqueScope("{$key}.unique_scope", $definition['unique_scope'] ?? null, $unique),
        );
    }

    /**
     * A `unique_scope` is only meaningful on a `unique => scope` definition: on any
     * other it would look like tenant isolation while the value stays unique per owner
     * type (or globally), so it throws instead of being ignored.
     *
     * @throws InvalidConfigurationException
     */
    private static function uniqueScope(string $key, mixed $scope, UniqueScope $unique): string|Closure|null
    {
        if ($scope === null) {
            return null;
        }

        if ($unique !== UniqueScope::Scope) {
            throw new InvalidConfigurationException(sprintf(
                "Configuration value [%s] needs the definition's unique set to 'scope', [%s] given.",
                $key,
                $unique->value,
            ));
        }

        return self::scopeResolver($key, $scope);
    }

    /**
     * A scope resolver as written in a definition or in `attributes.unique_scope`: a
     * non-blank attribute/relation name or class-string, or a closure.
     *
     * @throws InvalidConfigurationException
     */
    public static function scopeResolver(string $key, mixed $scope): string|Closure
    {
        if ($scope instanceof Closure || (is_string($scope) && trim($scope) !== '')) {
            return $scope;
        }

        throw new InvalidConfigurationException(sprintf(
            'Configuration value [%s] must be an attribute or relation name, a class-string or a closure, [%s] given.',
            $key,
            is_scalar($scope) ? var_export($scope, true) : get_debug_type($scope),
        ));
    }

    /**
     * @return list<string|object>
     */
    private static function rules(string $key, mixed $rules): array
    {
        if ($rules === null) {
            return [];
        }

        if (! is_array($rules)) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration value [%s] must be an array of validation rules, [%s] given.',
                $key,
                is_scalar($rules) ? var_export($rules, true) : get_debug_type($rules),
            ));
        }

        /** @var list<string|object> */
        return array_values($rules);
    }
}
