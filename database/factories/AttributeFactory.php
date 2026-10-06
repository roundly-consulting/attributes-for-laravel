<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Attributes\DataTransferObjects\StoredValue;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;

/**
 * @extends Factory<Attribute>
 */
final class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * @return array<model-property<Attribute>, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'value' => fake()->word(),
        ];
    }

    /**
     * A value stored in the given type — its storage form and `value_type` set
     * explicitly, since a type inferred from the PHP value would store `5` as an
     * integer even for a float.
     */
    public function ofType(AttributeType $type, mixed $value): self
    {
        return $this->state(fn (): array => [
            'value' => $value === null ? null : $type->toStorage($value),
            'value_type' => $type->value,
        ]);
    }

    public function integerValue(int $value = 1): self
    {
        return $this->ofType(AttributeType::Integer, $value);
    }

    public function booleanValue(bool $value = true): self
    {
        return $this->ofType(AttributeType::Boolean, $value);
    }

    /**
     * Store the value encrypted: the ciphertext and the flag together, so the model
     * reads it back. Applied once the model is made, when the stored form is known.
     */
    public function encrypted(): self
    {
        return $this->afterMaking(static function (Attribute $attribute): void {
            if ($attribute->is_encrypted) {
                return;
            }

            $raw = $attribute->getAttributes();
            $stored = $raw['value'] ?? null;

            $sealed = new AttributeValueCaster()->seal(
                new StoredValue($stored === null ? null : (string) $stored, $attribute->type()),
                true,
            );

            $attribute->setRawAttributes([...$raw, 'value' => $sealed->value, 'is_encrypted' => true]);
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        return $this->state(fn (): array => [
            'meta' => $meta,
        ]);
    }
}
