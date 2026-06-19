<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Models\AttributeRevision;

/**
 * @extends Factory<AttributeRevision>
 */
final class AttributeRevisionFactory extends Factory
{
    protected $model = AttributeRevision::class;

    /**
     * @return array<model-property<AttributeRevision>, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'type' => RevisionType::Attached,
            'old_value' => null,
            'new_value' => fake()->word(),
            'old_value_type' => null,
            'new_value_type' => AttributeType::String_->value,
        ];
    }

    public function ofType(RevisionType $type): self
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }
}
