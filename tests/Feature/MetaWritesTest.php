<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Events\AttributeAttached;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Review 2026-09-28: every value write did `forceFill(['meta' => $data->meta])` with a
 * null default, so setting a value without meta wiped the meta already stored.
 */
it('keeps meta when only the value is updated', function (): void {
    $product = Product::query()->create();

    Attributes::for($product)->set('color', 'red', meta: ['hex' => '#f00']);
    Attributes::for($product)->set('color', 'blue');
    $product->attachAttribute('color', 'green');
    Attributes::for($product)->setMany(['color' => 'black']);
    Attributes::for($product)->sync(['color' => 'white']);
    Attributes::for($product)->stage()->set('color', 'grey')->save();

    expect($product->getAttachedAttributeValue('color'))->toBe('grey')
        ->and($product->getAttachedAttributeMeta('color')?->all())->toBe(['hex' => '#f00']);
});

it('replaces meta when new meta is given with the value', function (): void {
    $product = Product::query()->create();

    Attributes::for($product)->set('color', 'red', meta: ['hex' => '#f00']);
    Attributes::for($product)->set('color', 'blue', meta: ['hex' => '#00f']);

    expect($product->getAttachedAttributeMeta('color')?->all())->toBe(['hex' => '#00f']);
});

it('clears meta through meta() with null', function (): void {
    $product = Product::query()->create();

    Attributes::for($product)->set('color', 'red', meta: ['hex' => '#f00']);
    Attributes::for($product)->meta('color', null);

    expect($product->getAttachedAttributeMeta('color'))->toBeNull()
        ->and($product->getAttachedAttributeValue('color'))->toBe('red');
});

/**
 * Review 2026-09-28: `meta()` bypassed events and history.
 */
it('fires AttributeAttached and records a revision for a meta() change', function (): void {
    config(['attributes.history.enabled' => true]);
    $product = Product::query()->create();
    Attributes::for($product)->set('color', 'red');

    Event::fake([AttributeAttached::class]);

    Attributes::for($product)->meta('color', ['hex' => '#f00']);

    Event::assertDispatched(AttributeAttached::class, fn (AttributeAttached $event): bool => $event->attribute->name === 'color'
        && $event->attribute->meta?->all() === ['hex' => '#f00']);

    $revision = $product->history('color')->first();

    expect($revision?->type)->toBe(RevisionType::Updated)
        ->and($revision?->old_value)->toBe('red')
        ->and($revision?->new_value)->toBe('red')
        ->and($revision?->old_meta)->toBeNull()
        ->and($revision?->new_meta?->all())->toBe(['hex' => '#f00'])
        ->and($product->getAttachedAttributeValue('color'))->toBe('red');
});

it('attaches a null-valued attribute through meta() and records it as attached', function (): void {
    config(['attributes.history.enabled' => true]);
    $product = Product::query()->create();

    $attribute = Attributes::for($product)->meta('color', ['hex' => '#f00']);

    expect($attribute->value)->toBeNull()
        ->and($product->history('color')->first()?->type)->toBe(RevisionType::Attached);
});
