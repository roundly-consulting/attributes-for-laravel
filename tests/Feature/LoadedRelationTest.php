<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Actions\AttachAttributeAction;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\DefinedProduct;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Chat review C-2: reads use an eager-loaded `attachedAttributes` relation when there is
 * one, but no write refreshed it — after `load('attachedAttributes')` a write was
 * invisible to the very model it was made on.
 */
it('reads an update made after the relation was loaded', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');
    $product->load('attachedAttributes');

    $product->attachAttribute('color', 'blue');

    expect($product->getAttachedAttributeValue('color'))->toBe('blue')
        ->and($product->getAttachedAttributes()->all())->toBe(['color' => 'blue']);
});

it('sees a newly attached name after the relation was loaded', function (): void {
    $product = Product::query()->create();
    $product->load('attachedAttributes');

    Attributes::for($product)->set('size', 'L');

    expect($product->hasAttachedAttribute('size'))->toBeTrue();
});

it('forgets a detached name after the relation was loaded', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('size', 'L');
    $product->load('attachedAttributes');

    $product->detachAttribute('size');

    expect($product->hasAttachedAttribute('size'))->toBeFalse();
});

it('refreshes the relation for sync, meta and the raw action too', function (): void {
    $product = Product::query()->create();
    $product->attachAttributes(['color' => 'red', 'size' => 'L']);

    $product->load('attachedAttributes');
    $product->syncAttributes(['color' => 'green']);

    expect($product->getAttachedAttributes()->all())->toBe(['color' => 'green']);

    $product->load('attachedAttributes');
    $product->syncAttributeMeta('color', collect(['hex' => '#0f0']));

    expect($product->getAttachedAttributeMeta('color')?->all())->toBe(['hex' => '#0f0']);

    $product->load('attachedAttributes');
    app(AttachAttributeAction::class)->execute($product, new AttributeData('color', 'teal'));

    expect($product->getAttachedAttributeValue('color'))->toBe('teal');
});

it('validates against the attributes written after the relation was loaded', function (): void {
    $product = DefinedProduct::query()->create();
    $product->load('attachedAttributes');

    $product->attachAttribute('rating', 4);
    $product->validateAttributes();

    expect($product->hasAttachedAttribute('rating'))->toBeTrue();
});
