<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
}
