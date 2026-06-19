<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('keeps a single live row under repeated upserts', function (): void {
    $product = Product::query()->create();

    // Simulate concurrent writers racing on (owner_type, owner_id, name).
    foreach (['a', 'b', 'c', 'd'] as $value) {
        $product->attachAttribute('color', $value);
    }

    $rows = Attribute::query()
        ->where('owner_type', $product->getMorphClass())
        ->where('owner_id', $product->getKey())
        ->where('name', 'color')
        ->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()?->value)->toBe('d');
});

it('does not duplicate the composite key across sync passes', function (): void {
    $product = Product::query()->create();

    $product->syncAttributes(['color' => 'white', 'size' => 'large']);
    $product->syncAttributes(['color' => 'black', 'size' => 'small']);

    $rows = Attribute::query()
        ->where('owner_type', $product->getMorphClass())
        ->where('owner_id', $product->getKey())
        ->get();

    $names = $rows->pluck('name')->all();

    expect($rows)->toHaveCount(2)
        ->and(array_unique($names))->toHaveCount(2)
        ->and($product->getAttachedAttributeValue('color'))->toBe('black');
});
