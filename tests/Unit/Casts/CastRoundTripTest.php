<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;

dataset('attribute-values', [
    'string' => ['white', AttributeType::String_],
    'empty string' => ['', AttributeType::String_],
    'unicode string' => ['café — déjà', AttributeType::String_],
    'integer' => [42, AttributeType::Integer],
    'zero integer' => [0, AttributeType::Integer],
    'large integer' => [9223372036854775807, AttributeType::Integer],
    'float' => [4.25, AttributeType::Float_],
    'zero float' => [0.0, AttributeType::Float_],
    'true' => [true, AttributeType::Boolean],
    'false' => [false, AttributeType::Boolean],
    'array' => [['hex' => '#fff'], AttributeType::Array_],
    'empty array' => [[], AttributeType::Array_],
    'nested array' => [['a' => ['b' => [1, 2, 3]]], AttributeType::Array_],
]);

it('round-trips every attribute type', function (mixed $value, AttributeType $type): void {
    $caster = new AttributeValueCaster;

    $stored = $caster->toStorage($value, $type);
    $restored = $caster->fromStorage($stored->value, $type);

    expect($restored)->toEqual($value)
        ->and($stored->type)->toBe($type)
        ->and($stored->encrypted)->toBeFalse();
})->with('attribute-values');

it('round-trips null for every type', function (AttributeType $type): void {
    $caster = new AttributeValueCaster;

    $stored = $caster->toStorage(null, $type);

    expect($stored->value)->toBeNull()
        ->and($caster->fromStorage(null, $type))->toBeNull();
})->with([
    [AttributeType::String_],
    [AttributeType::Integer],
    [AttributeType::Float_],
    [AttributeType::Boolean],
    [AttributeType::Array_],
    [AttributeType::DateTime],
]);

it('round-trips an ISO datetime', function (): void {
    $caster = new AttributeValueCaster;
    $when = Carbon::parse('2026-06-19T18:30:00+00:00');

    $stored = $caster->toStorage($when, AttributeType::DateTime);
    $restored = $caster->fromStorage($stored->value, AttributeType::DateTime);

    expect($restored->equalTo($when))->toBeTrue();
});

it('round-trips a value through encryption', function (): void {
    $caster = new AttributeValueCaster;

    $stored = $caster->toStorage('secret', AttributeType::String_, encrypt: true);

    expect($stored->encrypted)->toBeTrue()
        ->and($stored->value)->not->toBe('secret')
        ->and($caster->fromStorage($stored->value, AttributeType::String_, encrypted: true))->toBe('secret');
});

it('skips crypt for a null value even when asked to encrypt', function (): void {
    $stored = new AttributeValueCaster()->toStorage(null, AttributeType::String_, encrypt: true);

    expect($stored->value)->toBeNull()
        ->and($stored->encrypted)->toBeTrue();
});
