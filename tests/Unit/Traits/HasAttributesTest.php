<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Support\StoredAttributeValue;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('falls back to the defined default on a typed read', function (): void {
    Attributes::define(new AttributeDefinitionData('retries', AttributeType::Integer, default: 3));

    $product = Product::query()->create();

    expect($product->attributeInt('retries'))->toBe(3);

    $product->attachAttribute('retries', 9);

    expect($product->fresh()->attributeInt('retries'))->toBe(9);
});

it('returns null for a null default', function (): void {
    Attributes::define(new AttributeDefinitionData('note', AttributeType::String_, default: null));

    expect(Product::query()->create()->getAttachedAttributeValue('note'))->toBeNull();
});

it('applies defaults across every typed reader', function (): void {
    Attributes::defineMany(
        new AttributeDefinitionData('i', AttributeType::Integer, default: 7),
        new AttributeDefinitionData('f', AttributeType::Float_, default: 1.5),
        new AttributeDefinitionData('b', AttributeType::Boolean, default: true),
        new AttributeDefinitionData('a', AttributeType::Array_, default: ['x']),
        new AttributeDefinitionData('d', AttributeType::DateTime, default: '2026-06-19T00:00:00+00:00'),
    );

    $product = Product::query()->create();

    expect($product->attributeInt('i'))->toBe(7)
        ->and($product->attributeFloat('f'))->toBe(1.5)
        ->and($product->attributeBool('b'))->toBeTrue()
        ->and($product->attributeArray('a'))->toBe(['x'])
        ->and($product->attributeDate('d')?->toDateString())->toBe('2026-06-19');
});

it('omits unset defaults from the bulk export', function (): void {
    Attributes::define(new AttributeDefinitionData('retries', AttributeType::Integer, default: 3));

    $product = Product::query()->create();
    $product->attachAttribute('color', 'white');

    expect($product->getAttachedAttributes()->all())->toBe(['color' => 'white']);
});

it('builds a typed value object via attr', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('age', 21);

    expect($product->attr('age'))->toBeInstanceOf(StoredAttributeValue::class)
        ->and($product->attr('age')->int())->toBe(21)
        ->and($product->attr('missing')->isNull())->toBeTrue();
});

it('passes validateAttributes when nothing is required', function (): void {
    Product::query()->create()->validateAttributes();
})->throwsNoExceptions();
