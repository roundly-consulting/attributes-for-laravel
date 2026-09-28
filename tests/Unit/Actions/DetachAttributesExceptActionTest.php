<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Actions\DetachAttributesExceptAction;
use RoundlyConsulting\Attributes\Events\AttributeDetached;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('detaches every attribute but the kept ones and returns their names', function (): void {
    $product = Product::create();
    $product->attachAttributes(['color' => 'red', 'size' => 'L', 'price' => 10]);

    Event::fake([AttributeDetached::class]);

    $detached = app(DetachAttributesExceptAction::class)->execute($product, ['color']);

    expect($detached)->toBe(['size', 'price'])
        ->and($product->getAttachedAttributes()->keys()->all())->toBe(['color']);

    Event::assertDispatchedTimes(AttributeDetached::class, 2);
    $this->assertSoftDeleted('attributes', ['name' => 'size']);
});

it('purges trashed extras only when forcing', function (): void {
    $product = Product::create();
    $product->attachAttributes(['color' => 'red', 'size' => 'L']);
    $product->detachAttribute('size');

    app(DetachAttributesExceptAction::class)->execute($product, ['color']);

    $this->assertSoftDeleted('attributes', ['name' => 'size']);

    $detached = app(DetachAttributesExceptAction::class)->execute($product, ['color'], forceDelete: true);

    expect($detached)->toBe([]);
    $this->assertDatabaseMissing('attributes', ['name' => 'size']);
});
