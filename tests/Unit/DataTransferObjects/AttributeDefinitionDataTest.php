<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;

it('constructs with defaults', function (): void {
    $definition = new AttributeDefinitionData('rating', AttributeType::Integer);

    expect($definition->name)->toBe('rating')
        ->and($definition->type)->toBe(AttributeType::Integer)
        ->and($definition->rules)->toBe([])
        ->and($definition->default)->toBeNull()
        ->and($definition->required)->toBeFalse()
        ->and($definition->unique)->toBe(UniqueScope::None)
        ->and($definition->encrypted)->toBeFalse();
});

it('keeps unique scope and encrypted flag', function (): void {
    $definition = new AttributeDefinitionData(
        name: 'token',
        type: AttributeType::String_,
        unique: UniqueScope::Global_,
        encrypted: true,
    );

    expect($definition->unique)->toBe(UniqueScope::Global_)
        ->and($definition->encrypted)->toBeTrue();
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
