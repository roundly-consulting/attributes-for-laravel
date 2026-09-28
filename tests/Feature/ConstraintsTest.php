<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\MissingRequiredAttributeException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('blocks a duplicate owner-scoped value across rows', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Owner));

    Product::query()->create()->attachAttribute('sku', 'ABC');

    Product::query()->create()->attachAttribute('sku', 'ABC');
})->throws(DuplicateAttributeValueException::class);

it('allows re-saving the same owner value', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Owner));

    $product = Product::query()->create();
    $product->attachAttribute('sku', 'ABC');
    $product->attachAttribute('sku', 'ABC');

    expect($product->getAttachedAttributeValue('sku'))->toBe('ABC');
});

it('blocks a duplicate global value across owners', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    Product::query()->create()->attachAttribute('sku', 'XYZ');

    Product::query()->create()->attachAttribute('sku', 'XYZ');
})->throws(DuplicateAttributeValueException::class);

it('blocks a global duplicate even across different owner types', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    // A row owned by a different morph type holds the value first. (Written after
    // the Product's instead, the unique index itself rejects it.)
    Attribute::query()->create([
        'owner_type' => 'other-type',
        'owner_id' => 999,
        'name' => 'sku',
        'value' => 'ZZZ',
    ]);

    Product::query()->create()->attachAttribute('sku', 'ZZZ');
})->throws(DuplicateAttributeValueException::class);

it('throws when a required attribute is missing', function (): void {
    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer, required: true));

    Product::query()->create()->validateAttributes();
})->throws(MissingRequiredAttributeException::class);

it('passes validation when required attributes are present', function (): void {
    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer, rules: ['min:1', 'max:5'], required: true));

    $product = Product::query()->create();
    $product->attachAttribute('rating', 4);

    $product->validateAttributes();
})->throwsNoExceptions();

it('leaves non-unique attributes unaffected', function (): void {
    $a = Product::query()->create();
    $b = Product::query()->create();

    $a->attachAttribute('color', 'white');
    $b->attachAttribute('color', 'white');

    expect($b->getAttachedAttributeValue('color'))->toBe('white');
});
