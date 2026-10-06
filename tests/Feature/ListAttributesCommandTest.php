<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
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

/**
 * Chat review C-18: the signature takes the "owner morph type or class", but a class name
 * was compared as-is — under a morph map the rows carry the alias, so nothing was listed.
 */
it('lists an owner by its class name under a morph map', function (): void {
    Relation::morphMap(['product' => Product::class]);

    try {
        $product = Product::create();
        $product->attachAttribute('color', 'white');

        $this->artisan('attributes:list', ['owner-type' => Product::class, 'owner-id' => $product->getKey()])
            ->expectsOutputToContain('white')
            ->doesntExpectOutputToContain('No attributes found')
            ->assertSuccessful();

        $this->artisan('attributes:list', ['owner-type' => 'product', 'owner-id' => $product->getKey()])
            ->expectsOutputToContain('white')
            ->assertSuccessful();
    } finally {
        Relation::morphMap([], merge: false);
    }
});
