<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;

/**
 * @implements CastsAttributes<mixed, mixed>
 */
final class AttributeValue implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $stored = $attributes['value'] ?? null;

        $type = $this->resolveType($attributes['value_type'] ?? null);

        return new AttributeValueCaster()->fromStorage(
            $stored === null ? null : (string) $stored,
            $type,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $stored = new AttributeValueCaster()->toStorage($value);

        return [
            'value' => $stored->value,
            'value_type' => $stored->type->value,
        ];
    }

    private function resolveType(mixed $rawType): AttributeType
    {
        if (is_string($rawType)) {
            return AttributeType::tryFrom($rawType) ?? AttributeType::String_;
        }

        return AttributeType::String_;
    }
}
