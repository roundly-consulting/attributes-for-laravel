<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Actions\DetachAttributesAction;
use RoundlyConsulting\Attributes\Events\AttributeDetached;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('soft-deletes the named attributes and dispatches events', function (): void {
    $product = Product::create();
    $product->attachAttributes(['color' => 'white', 'size' => 'small']);

    Event::fake();

    $deleted = app(DetachAttributesAction::class)->execute($product, ['color', 'size']);

    expect($deleted)->toBe(2);

    $this->assertSoftDeleted('attributes', ['name' => 'color']);
    Event::assertDispatchedTimes(AttributeDetached::class, 2);
});

it('force-deletes when requested', function (): void {
    $product = Product::create();
    $product->attachAttribute('color', 'white');

    app(DetachAttributesAction::class)->execute($product, ['color'], true);

    $this->assertDatabaseMissing('attributes', ['name' => 'color']);
});

it('returns zero and dispatches nothing for an empty list', function (): void {
    Event::fake();
    $product = Product::create();

    expect(app(DetachAttributesAction::class)->execute($product, []))->toBe(0);

    Event::assertNotDispatched(AttributeDetached::class);
});
