<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Models\Attribute;

it('builds an attribute via its factory', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
    ]);

    expect($attribute)
        ->toBeInstanceOf(Attribute::class)
        ->name->toBeString()
        ->value->toBeString();
});

it('casts the meta column to a collection', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'meta' => ['featured' => true],
    ]);

    expect($attribute->meta)
        ->toBeInstanceOf(Collection::class)
        ->toArray()->toBe(['featured' => true]);
});
