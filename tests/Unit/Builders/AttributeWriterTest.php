<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Tests\Models\Product;

it('accumulates typed writes and persists them on save', function (): void {
    $product = Product::create();

    $returned = $product->attributes()
        ->set('color', 'white')
        ->set('rating', 5)
        ->set('on_sale', true)
        ->meta('color', ['hex' => '#fff'])
        ->save();

    expect($returned)->toBe($product)
        ->and($product->getAttachedAttributeValue('rating'))->toBe(5)
        ->and($product->getAttachedAttributeValue('on_sale'))->toBeTrue()
        ->and($product->getAttachedAttributeMeta('color')?->get('hex'))->toBe('#fff');
});

it('does nothing on an empty save', function (): void {
    $product = Product::create();

    $product->attributes()->save();

    expect($product->attachedAttributes()->count())->toBe(0);
});

it('accepts meta as a collection', function (): void {
    $product = Product::create();

    $product->attributes()
        ->set('color', 'white')
        ->meta('color', collect(['hex' => '#000']))
        ->save();

    expect($product->getAttachedAttributeMeta('color')?->get('hex'))->toBe('#000');
});

it('forgets a staged attribute before saving', function (): void {
    $product = Product::create();

    $product->attributes()
        ->set('color', 'white')
        ->set('size', 'small')
        ->forget('size')
        ->save();

    expect($product->hasAttachedAttribute('color'))->toBeTrue()
        ->and($product->hasAttachedAttribute('size'))->toBeFalse();
});

it('keeps only the named staged attributes', function (): void {
    $product = Product::create();

    $product->attributes()
        ->set('color', 'white')
        ->set('size', 'small')
        ->set('rating', 5)
        ->only('color', 'rating')
        ->save();

    expect($product->hasAttachedAttribute('color'))->toBeTrue()
        ->and($product->hasAttachedAttribute('rating'))->toBeTrue()
        ->and($product->hasAttachedAttribute('size'))->toBeFalse();
});

it('drops named staged attributes with except', function (): void {
    $product = Product::create();

    $product->attributes()
        ->set('color', 'white')
        ->set('size', 'small')
        ->except('size')
        ->save();

    expect($product->hasAttachedAttribute('size'))->toBeFalse();
});

it('replaces all attributes via sync', function (): void {
    $product = Product::create();
    $product->attachAttributes(['color' => 'white', 'size' => 'small']);

    $product->attributes()
        ->set('size', 'large')
        ->sync();

    $this->assertSoftDeleted('attributes', ['name' => 'color']);
    expect($product->getAttachedAttributeValue('size'))->toBe('large');
});
