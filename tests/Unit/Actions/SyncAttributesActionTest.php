<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Actions\SyncAttributesAction;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Events\AttributesSynced;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('removes absent attributes and keeps the synced set', function (): void {
    $product = Product::create();
    $product->attachAttributes(['color' => 'white', 'size' => 'small']);

    Event::fake();

    app(SyncAttributesAction::class)->execute($product, [
        new AttributeData('size', 'large'),
    ]);

    $this->assertSoftDeleted('attributes', ['name' => 'color']);
    expect($product->getAttachedAttributeValue('size'))->toBe('large');

    Event::assertDispatched(AttributesSynced::class, fn (AttributesSynced $event): bool => $event->owner->is($product) && count($event->attributes) === 1);
});

it('force-deletes removed attributes when requested', function (): void {
    $product = Product::create();
    $product->attachAttributes(['color' => 'white', 'size' => 'small']);

    app(SyncAttributesAction::class)->execute($product, [
        new AttributeData('size', 'large'),
    ], true);

    $this->assertDatabaseMissing('attributes', ['name' => 'color']);
});
