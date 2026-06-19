<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\Casts\AttributeValue;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

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

it('writes plaintext and false is_encrypted for undefined attributes', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'name' => 'color',
        'value' => 'white',
    ]);

    $row = DB::table('attributes')->where('id', $attribute->id)->first();

    expect($row->value)->toBe('white')
        ->and((int) $row->is_encrypted)->toBe(0);
});

it('encrypts a value defined as encrypted and decrypts it on read', function (): void {
    Attributes::define(
        new AttributeDefinitionData(
            'token',
            AttributeType::String_,
            encrypted: true,
        ),
    );

    $product = Product::query()->create();
    $product->attachAttribute('token', 'hunter2');

    $row = DB::table('attributes')->where('name', 'token')->first();

    expect($row->value)->not->toBe('hunter2')
        ->and((int) $row->is_encrypted)->toBe(1)
        ->and($product->fresh()->getAttachedAttributeValue('token'))->toBe('hunter2');
});

it('leaves a row plaintext when the name is missing from attributes', function (): void {
    // Direct cast read with no name in attributes resolves to no definition.
    $cast = new AttributeValue;
    $model = new Attribute;

    $result = $cast->set($model, 'value', 'plain', []);

    expect($result['is_encrypted'])->toBeFalse();
});

it('rebuilds the owner from morph type when the relation is not loaded', function (): void {
    Attributes::define(
        new AttributeDefinitionData(
            'token',
            AttributeType::String_,
            encrypted: true,
        ),
    );

    $cast = new AttributeValue;
    $model = new Attribute;

    $result = $cast->set($model, 'value', 'secret', [
        'name' => 'token',
        'owner_type' => Product::class,
        'owner_id' => 1,
    ]);

    expect($result['is_encrypted'])->toBeTrue()
        ->and($result['value'])->not->toBe('secret');
});
