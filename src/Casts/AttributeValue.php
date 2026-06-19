<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
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

        $encrypted = (bool) ($attributes['is_encrypted'] ?? false);

        return new AttributeValueCaster()->fromStorage(
            $stored === null ? null : (string) $stored,
            $type,
            $encrypted,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $encrypt = $this->shouldEncrypt($model, $attributes);

        $stored = new AttributeValueCaster()->toStorage($value, null, $encrypt);

        return [
            'value' => $stored->value,
            'value_type' => $stored->type->value,
            'is_encrypted' => $stored->encrypted,
        ];
    }

    /**
     * Resolve the per-definition encryption flag from the registry by name.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function shouldEncrypt(Model $model, array $attributes): bool
    {
        $name = $attributes['name'] ?? null;

        if (! is_string($name)) {
            return false;
        }

        $definition = app(AttributeRegistry::class)->resolveFor(
            $this->resolveOwner($model, $attributes),
            $name,
        );

        return $definition !== null && $definition->encrypted;
    }

    /**
     * Resolve the owner instance so per-model definitions are honoured.
     *
     * Uses the already-loaded relation when present, otherwise rebuilds a
     * keyless owner instance from the morph type so model-declared
     * definitions (a method/property) can be read without a DB query.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveOwner(Model $model, array $attributes): ?Model
    {
        if ($model->relationLoaded('owner')) {
            $loaded = $model->getRelation('owner');

            return $loaded instanceof Model ? $loaded : null;
        }

        $type = $attributes['owner_type'] ?? null;

        if (! is_string($type) || $type === '') {
            return null;
        }

        /** @var class-string<Model>|null $class */
        $class = Relation::getMorphedModel($type) ?? (class_exists($type) ? $type : null);

        if ($class === null) {
            return null;
        }

        return new $class;
    }

    private function resolveType(mixed $rawType): AttributeType
    {
        if (is_string($rawType)) {
            return AttributeType::tryFrom($rawType) ?? AttributeType::String_;
        }

        return AttributeType::String_;
    }
}
