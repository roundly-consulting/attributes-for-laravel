<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\Product;

beforeEach(function (): void {
    $this->product = Product::query()->create();
    $this->product->attachAttributes(['color' => 'white', 'size' => 'large']);
});

it('returns the same map as getAttachedAttributes', function (): void {
    expect(Attributes::for($this->product)->all()->all())
        ->toBe($this->product->getAttachedAttributes()->all());
});

it('exposes a key/value array', function (): void {
    expect(Attributes::for($this->product)->toKeyValue())
        ->toBe(['color' => 'white', 'size' => 'large']);
});

it('lists the attribute keys', function (): void {
    expect(Attributes::for($this->product)->keys())
        ->toContain('color')
        ->toContain('size');
});

it('reads a single value and presence', function (): void {
    $query = Attributes::for($this->product);

    expect($query->get('color'))->toBe('white')
        ->and($query->has('color'))->toBeTrue()
        ->and($query->has('missing'))->toBeFalse();
});
