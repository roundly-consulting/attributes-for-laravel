<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\DefinedProduct;
use RoundlyConsulting\Attributes\Tests\Models\MethodProduct;
use RoundlyConsulting\Attributes\Tests\Models\Product;

beforeEach(function (): void {
    config(['attributes.strict' => true]);
});

/**
 * Review 2026-09-28: strict mode only consulted the global definitions, so a name a model
 * declared in its own `$attributeDefinitions` was rejected as unknown.
 */
it('accepts a model-declared attribute in strict mode', function (): void {
    $product = DefinedProduct::query()->create();
    $product->attachAttribute('sku', 'X1');

    $method = MethodProduct::query()->create();
    $method->attachAttribute('level', 4);

    expect($product->getAttachedAttributeValue('sku'))->toBe('X1')
        ->and($method->getAttachedAttributeValue('level'))->toBe(4)
        ->and(Attributes::isStrict())->toBeTrue();
});

it('still rejects a name the model does not declare in strict mode', function (): void {
    expect(fn () => DefinedProduct::query()->create()->attachAttribute('unknown', 'x'))
        ->toThrow(UnknownAttributeException::class, 'Attribute [unknown] is not registered.');
});

it('does not let one model schema leak into another in strict mode', function (): void {
    expect(fn () => Product::query()->create()->attachAttribute('sku', 'X1'))
        ->toThrow(UnknownAttributeException::class);
});

it('checks the owner schema through the facade assertKnown()', function (): void {
    $owner = DefinedProduct::query()->create();

    Attributes::assertKnown('sku', $owner);

    expect(fn () => Attributes::assertKnown('sku'))->toThrow(UnknownAttributeException::class);
});

/**
 * Review 2026-09-28: `meta()` was a plain updateOrCreate and created an unregistered
 * attribute even with strict mode on.
 */
it('rejects meta() for an unknown attribute in strict mode', function (): void {
    $product = Product::query()->create();

    expect(fn () => Attributes::for($product)->meta('totally_unknown', ['a' => 1]))
        ->toThrow(UnknownAttributeException::class);

    expect($product->hasAttachedAttribute('totally_unknown'))->toBeFalse();
});

it('accepts meta() for a model-declared attribute in strict mode', function (): void {
    $product = DefinedProduct::query()->create();

    Attributes::for($product)->meta('sku', ['source' => 'erp']);

    expect($product->getAttachedAttributeMeta('sku')?->all())->toBe(['source' => 'erp']);
});

it('accepts sync() and setMany() for model-declared attributes in strict mode', function (): void {
    $product = DefinedProduct::query()->create();

    Attributes::for($product)->setMany(['sku' => 'A', 'rating' => 3]);
    Attributes::for($product)->sync(['sku' => 'B']);

    expect($product->getAttachedAttributes()->all())->toBe(['sku' => 'B']);
});
