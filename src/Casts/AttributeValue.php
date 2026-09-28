<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;
use RoundlyConsulting\Attributes\Support\UniqueIndex;

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
     * Store a value in its definition's type (inferred from the PHP value only when
     * the name has no definition), encrypted when the definition says so, with the
     * `unique_hash` a unique definition's index is enforced on.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $name = is_string($attributes['name'] ?? null) ? $attributes['name'] : null;

        $definition = $name === null
            ? null
            : app(AttributeRegistry::class)->resolveFor($this->resolveOwner($model, $attributes), $name);

        $caster = new AttributeValueCaster;

        try {
            $plain = $caster->plain($value, $definition?->type);
        } catch (InvalidAttributeValueException $exception) {
            throw InvalidAttributeValueException::forName(
                $name ?? $key,
                'expected a value of type ['.($definition?->type->value ?? AttributeType::forValue($value)->value).']',
                $exception,
            );
        }

        $stored = $caster->seal($plain, $definition !== null && $definition->encrypted);

        $ownerType = $attributes['owner_type'] ?? null;

        return [
            'value' => $stored->value,
            'value_type' => $stored->type->value,
            'is_encrypted' => $stored->encrypted,
            'unique_hash' => $definition === null
                ? null
                : UniqueIndex::for($definition, is_string($ownerType) ? $ownerType : null, $plain->value),
        ];
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
