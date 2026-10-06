<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Chat review C-3: a write to an owner that was never saved inserted a row with
 * `owner_id` NULL — unreachable once the owner is saved, and for a unique value a slot
 * held for good.
 */
it('refuses to write to an owner that is not saved yet', function (): void {
    expect(fn () => (new Product)->attachAttribute('color', 'blue'))
        ->toThrow(AttributesException::class, 'Attribute [color] cannot be written to an unsaved owner')
        ->and(Attribute::withTrashed()->count())->toBe(0);
});

it('refuses every write path on an unsaved owner', function (Closure $write): void {
    expect(fn () => $write(new Product))->toThrow(AttributesException::class, 'unsaved owner')
        ->and(Attribute::withTrashed()->count())->toBe(0);
})->with([
    'set' => fn (Product $owner) => Attributes::for($owner)->set('color', 'blue'),
    'setMany' => fn (Product $owner) => Attributes::for($owner)->setMany(['color' => 'blue']),
    'sync' => fn (Product $owner) => Attributes::for($owner)->sync(['color' => 'blue']),
    'meta' => fn (Product $owner) => Attributes::for($owner)->meta('color', ['hex' => '#00f']),
]);

it('refuses a deleted owner', function (): void {
    $product = Product::query()->create();
    $product->delete();

    expect(fn () => $product->attachAttribute('color', 'blue'))->toThrow(AttributesException::class, 'unsaved owner');
});

it('leaves a unique value free for a saved owner', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    expect(fn () => (new Product)->attachAttribute('sku', 'X-1'))->toThrow(AttributesException::class);

    $saved = Product::query()->create();
    $saved->attachAttribute('sku', 'X-1');

    expect($saved->getAttachedAttributeValue('sku'))->toBe('X-1');
});
