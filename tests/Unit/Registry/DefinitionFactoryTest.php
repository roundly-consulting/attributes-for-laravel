<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Registry\DefinitionFactory;

it('parses every definition key', function (): void {
    $definition = DefinitionFactory::fromArray('rating', [
        'type' => 'integer',
        'rules' => ['min:1', 'max:5'],
        'required' => true,
        'default' => 3,
        'unique' => 'global',
        'encrypted' => true,
    ]);

    expect($definition->name)->toBe('rating')
        ->and($definition->type)->toBe(AttributeType::Integer)
        ->and($definition->rules)->toBe(['min:1', 'max:5'])
        ->and($definition->required)->toBeTrue()
        ->and($definition->default)->toBe(3)
        ->and($definition->unique)->toBe(UniqueScope::Global_)
        ->and($definition->encrypted)->toBeTrue();
});

it('applies sensible defaults for a minimal definition', function (): void {
    $definition = DefinitionFactory::fromArray('color', []);

    expect($definition->type)->toBe(AttributeType::String_)
        ->and($definition->rules)->toBe([])
        ->and($definition->required)->toBeFalse()
        ->and($definition->default)->toBeNull()
        ->and($definition->unique)->toBe(UniqueScope::None)
        ->and($definition->encrypted)->toBeFalse();
});

it('falls back to string for an unknown type and ignores bad rules', function (): void {
    $definition = DefinitionFactory::fromArray('weird', [
        'type' => 'nope',
        'rules' => 'not-an-array',
    ]);

    expect($definition->type)->toBe(AttributeType::String_)
        ->and($definition->rules)->toBe([]);
});

it('maps a boolean unique flag to owner scope', function (): void {
    expect(DefinitionFactory::fromArray('sku', ['unique' => true])->unique)
        ->toBe(UniqueScope::Owner);
});
