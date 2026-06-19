<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Models\Attribute;

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

    public function ofType(AttributeType $type, mixed $value): self
    {
        // The model cast derives the stored form and value_type from the typed value.
        return $this->state(fn (): array => [
            'value' => $value,
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
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        return $this->state(fn (): array => [
            'meta' => $meta,
        ]);
    }
}
