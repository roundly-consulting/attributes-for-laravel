<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Enums\UniqueScope;

it('parses unique scope from config strings', function (): void {
    expect(UniqueScope::fromConfig('owner'))->toBe(UniqueScope::Owner)
        ->and(UniqueScope::fromConfig('global'))->toBe(UniqueScope::Global_)
        ->and(UniqueScope::fromConfig('none'))->toBe(UniqueScope::None);
});

it('parses unique scope from booleans and null', function (): void {
    expect(UniqueScope::fromConfig(true))->toBe(UniqueScope::Owner)
        ->and(UniqueScope::fromConfig(false))->toBe(UniqueScope::None)
        ->and(UniqueScope::fromConfig(null))->toBe(UniqueScope::None)
        ->and(UniqueScope::fromConfig(42))->toBe(UniqueScope::None);
});

it('passes through an existing enum', function (): void {
    expect(UniqueScope::fromConfig(UniqueScope::Global_))->toBe(UniqueScope::Global_);
});

it('falls back to none for an unknown string', function (): void {
    expect(UniqueScope::fromConfig('whatever'))->toBe(UniqueScope::None);
});

it('reports whether it enforces uniqueness', function (): void {
    expect(UniqueScope::None->enforces())->toBeFalse()
        ->and(UniqueScope::Owner->enforces())->toBeTrue()
        ->and(UniqueScope::Global_->enforces())->toBeTrue();
});

it('exposes the backed values and labels', function (): void {
    expect(UniqueScope::values()->all())->toBe(['none', 'owner', 'global'])
        ->and(UniqueScope::toOptions()->all())->toBe([
            'none' => 'None',
            'owner' => 'Owner',
            'global' => 'Global',
        ]);
});

it('exposes the trait membership validation rule', function (): void {
    expect(UniqueScope::validationRule())->toBe('in:none,owner,global');
});
