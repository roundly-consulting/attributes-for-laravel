<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Tests\Models\Product;

it('lists the attributes for an owner', function (): void {
    $product = Product::create();
    $product->attributes()
        ->set('color', 'white')
        ->set('rating', 5)
        ->meta('color', ['hex' => '#fff'])
        ->save();

    $this->artisan('attributes:list', [
        'owner-type' => $product->getMorphClass(),
        'owner-id' => $product->getKey(),
    ])
        ->expectsOutputToContain('color')
        ->expectsOutputToContain('rating')
        ->assertSuccessful();
});

it('renders array, boolean and null values', function (): void {
    $product = Product::create();
    $product->attributes()
        ->set('tags', ['a', 'b'])
        ->set('on_sale', true)
        ->set('note', null)
        ->set('published_at', now())
        ->save();

    $this->artisan('attributes:list', [
        'owner-type' => $product->getMorphClass(),
        'owner-id' => $product->getKey(),
    ])
        ->expectsOutputToContain('true')
        ->expectsOutputToContain('tags')
        ->assertSuccessful();
});

it('reports when an owner has no attributes', function (): void {
    $product = Product::create();

    $this->artisan('attributes:list', [
        'owner-type' => $product->getMorphClass(),
        'owner-id' => $product->getKey(),
    ])
        ->expectsOutputToContain('No attributes found')
        ->assertSuccessful();
});
