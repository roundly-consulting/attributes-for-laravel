<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

beforeEach(function (): void {
    $this->white = Product::create();
    $this->white->attributes()->set('color', 'white')->set('rating', 5)->set('on_sale', true)->save();

    $this->black = Product::create();
    $this->black->attributes()->set('color', 'black')->set('rating', 3)->save();
});

it('filters by attribute value', function (): void {
    $results = Product::query()->whereAttribute('color', 'white')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->is($this->white))->toBeTrue();
});

it('matches typed equality', function (): void {
    $results = Product::query()->whereAttribute('rating', 5)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->is($this->white))->toBeTrue();
});

it('filters by a set of values', function (): void {
    $results = Product::query()->whereAttributeIn('rating', [3, 5])->get();

    expect($results)->toHaveCount(2);
});

it('filters owners that have an attribute', function (): void {
    $results = Product::query()->whereHasAttribute('on_sale')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->is($this->white))->toBeTrue();
});

it('filters owners that do not have an attribute', function (): void {
    $results = Product::query()->whereDoesntHaveAttribute('on_sale')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->is($this->black))->toBeTrue();
});

it('orders owners by an attribute value', function (): void {
    $asc = Product::query()->orderByAttribute('rating', 'asc')->get();
    $desc = Product::query()->orderByAttribute('rating', 'desc')->get();

    expect($asc->first()?->is($this->black))->toBeTrue()
        ->and($desc->first()?->is($this->white))->toBeTrue();
});

it('scopes attributes for a given owner', function (): void {
    expect(Attribute::query()->forOwner($this->white)->count())->toBe(3)
        ->and(Attribute::query()->forOwner($this->black)->count())->toBe(2);
});
