<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Models\Attribute;

it('persists and reads typed values through the model', function (mixed $value): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'value' => $value,
    ]);

    expect($attribute->fresh()->value)->toEqual($value);
})->with([
    'string' => ['white'],
    'integer' => [5],
    'float' => [4.25],
    'boolean true' => [true],
    'boolean false' => [false],
    'array' => [['hex' => '#fff']],
]);

it('persists the value_type column from the written value', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'value' => 5,
    ]);

    expect($attribute->fresh()->value_type)->toBe(AttributeType::Integer->value)
        ->and($attribute->fresh()->type())->toBe(AttributeType::Integer);
});

it('round-trips datetime values', function (): void {
    $when = Carbon::parse('2026-06-19 18:30:00');

    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'value' => $when,
    ]);

    expect($attribute->fresh()->value->equalTo($when))->toBeTrue()
        ->and($attribute->fresh()->value_type)->toBe(AttributeType::DateTime->value);
});

it('reads legacy untyped string rows as strings', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
    ]);

    // Simulate a row written before typing existed.
    $attribute->forceFill(['value' => 'legacy', 'value_type' => null])->saveQuietly();

    expect($attribute->fresh()->value)->toBe('legacy');
});

it('returns null for a null value', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'value' => null,
    ]);

    expect($attribute->fresh()->value)->toBeNull();
});
