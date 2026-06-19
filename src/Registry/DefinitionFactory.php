<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Registry;

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;

/**
 * Parses a raw definition array (from config or a model) into a DTO.
 *
 * Shared by the service provider (config path) and the registry
 * (per-model path) so both honour exactly the same schema keys.
 */
final class DefinitionFactory
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(string $name, array $definition): AttributeDefinitionData
    {
        $type = $definition['type'] ?? AttributeType::String_->value;

        /** @var list<string|object> $rules */
        $rules = is_array($definition['rules'] ?? null) ? array_values($definition['rules']) : [];

        return new AttributeDefinitionData(
            name: $name,
            type: is_string($type) ? AttributeType::tryFrom($type) ?? AttributeType::String_ : AttributeType::String_,
            rules: $rules,
            default: $definition['default'] ?? null,
            required: (bool) ($definition['required'] ?? false),
            unique: UniqueScope::fromConfig($definition['unique'] ?? null),
            encrypted: (bool) ($definition['encrypted'] ?? false),
        );
    }
}
