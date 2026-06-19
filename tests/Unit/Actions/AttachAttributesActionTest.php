<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Actions\AttachAttributesAction;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Events\AttributeAttached;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('attaches multiple attributes and dispatches an event per item', function (): void {
    Event::fake();
    $product = Product::create();

    $attributes = app(AttachAttributesAction::class)->execute(
        $product,
        new AttributeData('color', 'white'),
        new AttributeData('rating', 5),
    );

    expect($attributes)->toHaveCount(2);

    Event::assertDispatchedTimes(AttributeAttached::class, 2);
});

it('returns an empty collection when no data is given', function (): void {
    $product = Product::create();

    expect(app(AttachAttributesAction::class)->execute($product))->toHaveCount(0);
});
