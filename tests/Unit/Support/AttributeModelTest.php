<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Support\AttributeModel;
use RoundlyConsulting\Attributes\Tests\Models\CustomAttribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(AttributeModel::class())->toBe(Attribute::class);
});

it('resolves a host subclass configured on attributes.model', function (): void {
    config()->set('attributes.model', CustomAttribute::class);

    expect(AttributeModel::class())->toBe(CustomAttribute::class);
});

it('attaches through the configured host model', function (): void {
    config()->set('attributes.model', CustomAttribute::class);

    $product = Product::query()->create();
    $product->attachAttribute('sku', 'ABC-1');

    expect($product->attachedAttributes()->first())->toBeInstanceOf(CustomAttribute::class);
});

it('falls back to the packaged model when the configured model is not an attribute', function (): void {
    config()->set('attributes.model', Product::class);

    expect(AttributeModel::class())->toBe(Attribute::class);
});

it('rejects a configured value that is not an eloquent model', function (): void {
    config()->set('attributes.model', 'NotAModel');

    AttributeModel::class();
})->throws(InvalidConfigurationException::class);
