<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\Attributes\Tests\Models\SoftDeletingProduct;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Chat review C-4 (owner: option A): a permanently deleted owner took nothing with it —
 * its rows stayed behind, and its unique values stayed taken for good.
 */
function attributeRowsOf(Product|SoftDeletingProduct $owner): int
{
    return Attribute::withTrashed()
        ->where('owner_type', $owner->getMorphClass())
        ->where('owner_id', $owner->getKey())
        ->count();
}

it('removes the attribute rows of a permanently deleted owner', function (): void {
    $product = Product::query()->create();
    $product->attachAttributes(['color' => 'red', 'size' => 'L']);
    $product->detachAttribute('size');

    $product->delete();

    expect(attributeRowsOf($product))->toBe(0);
});

it('frees a deleted owner\'s unique value for the next owner', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    $first = Product::query()->create();
    $first->attachAttribute('sku', 'X');
    $first->delete();

    $second = Product::query()->create();
    $second->attachAttribute('sku', 'X');

    expect($second->getAttachedAttributeValue('sku'))->toBe('X');
});

it('keeps the rows of a soft-deleted owner and removes them on force delete', function (): void {
    $product = SoftDeletingProduct::query()->create();
    $product->attachAttribute('color', 'red');

    $product->delete();

    expect(attributeRowsOf($product))->toBe(1)
        ->and($product->getAttachedAttributeValue('color'))->toBe('red');

    $product->forceDelete();

    expect(attributeRowsOf($product))->toBe(0);
});

it('keeps the revision history of a deleted owner', function (): void {
    config()->set('attributes.history.enabled', true);

    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    $product->delete();

    expect(AttributeRevision::query()->where('owner_id', $product->getKey())->orderBy('id')->pluck('type')->map->value->all())
        ->toBe(['attached', 'detached']);
});

it('goes through the facade, so the fake records the removal', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');
    $fake = Attributes::fake();

    $product->delete();

    $fake->assertForgotten($product, 'color');
});

it('keeps the rows when delete_with_owner is off', function (): void {
    config()->set('attributes.delete_with_owner', false);

    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    $product->delete();

    expect(attributeRowsOf($product))->toBe(1);
});

it('refuses a junk delete_with_owner (strict config)', function (): void {
    config()->set('attributes.delete_with_owner', 'sometimes');

    $product = Product::query()->create();

    expect(fn () => $product->delete())->toThrow(InvalidConfigurationException::class, 'attributes.delete_with_owner');
});
