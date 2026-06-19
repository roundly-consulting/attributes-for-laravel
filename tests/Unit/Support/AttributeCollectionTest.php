<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Support\AttributeCollection;
use RoundlyConsulting\Attributes\Tests\Models\Product;

beforeEach(function (): void {
    $this->product = Product::query()->create();
    $this->product->attachAttributes(['color' => 'white', 'size' => 'large']);
});

it('returns an AttributeCollection from queries and relations', function (): void {
    expect(Attribute::query()->get())->toBeInstanceOf(AttributeCollection::class)
        ->and($this->product->attachedAttributes()->get())->toBeInstanceOf(AttributeCollection::class)
        ->and(Attribute::collect())->toBeInstanceOf(AttributeCollection::class);
});

it('flattens to a key value map', function (): void {
    $map = $this->product->attachedAttributes()->get()->toKeyValue();

    expect($map)->toBe(['color' => 'white', 'size' => 'large']);
});

it('keeps the last value when names collide', function (): void {
    $collection = new AttributeCollection([
        Attribute::factory()->make(['name' => 'color', 'value' => 'white']),
        Attribute::factory()->make(['name' => 'color', 'value' => 'black']),
    ]);

    expect($collection->toKeyValue())->toBe(['color' => 'black']);
});

it('keys the collection by name', function (): void {
    $byName = Attribute::collect()->keyByName();

    expect($byName->keys()->all())->toContain('color')->toContain('size')
        ->and($byName->get('color')?->name)->toBe('color');
});
