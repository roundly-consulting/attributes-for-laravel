<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Registry;

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

        return new AttributeDefinitionData(
            name: $name,
            type: $read->enum("{$key}.type", AttributeType::class, AttributeType::String_),
            rules: self::rules("{$key}.rules", $definition['rules'] ?? null),
            default: $definition['default'] ?? null,
            required: $read->boolean("{$key}.required"),
            unique: UniqueScope::fromConfig($definition['unique'] ?? null, "{$key}.unique"),
            encrypted: $read->boolean("{$key}.encrypted"),
        );
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
