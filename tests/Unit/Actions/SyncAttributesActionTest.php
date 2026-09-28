<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Actions\SyncAttributesAction;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Events\AttributeDetached;
use RoundlyConsulting\Attributes\Events\AttributesSynced;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('removes absent attributes and keeps the synced set', function (): void {
    $product = Product::create();
    $product->attachAttributes(['color' => 'white', 'size' => 'small']);

    Event::fake();

    $written = app(SyncAttributesAction::class)->execute($product, [
        new AttributeData('size', 'large'),
    ]);

    $this->assertSoftDeleted('attributes', ['name' => 'color']);
    expect($product->getAttachedAttributeValue('size'))->toBe('large')
        ->and($written)->toHaveCount(1)
        ->and($written->first())->toBeInstanceOf(Attribute::class)
        ->and($written->first()?->name)->toBe('size');

    Event::assertDispatched(AttributeDetached::class, fn (AttributeDetached $event): bool => $event->name === 'color');

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
