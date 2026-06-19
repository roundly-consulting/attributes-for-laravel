<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;

it('constructs with defaults', function (): void {
    $definition = new AttributeDefinitionData('rating', AttributeType::Integer);

    expect($definition->name)->toBe('rating')
        ->and($definition->type)->toBe(AttributeType::Integer)
        ->and($definition->rules)->toBe([])
        ->and($definition->default)->toBeNull()
        ->and($definition->required)->toBeFalse();
});

it('keeps rules, default and required', function (): void {
    $definition = new AttributeDefinitionData(
        name: 'rating',
        type: AttributeType::Integer,
        rules: ['min:1', 'max:5'],
        default: 3,
        required: true,
    );

    expect($definition->rules)->toBe(['min:1', 'max:5'])
        ->and($definition->default)->toBe(3)
        ->and($definition->required)->toBeTrue();
});
