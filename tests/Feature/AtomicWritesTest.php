<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Events\AttributeAttached;
use RoundlyConsulting\Attributes\Events\AttributeDetached;
use RoundlyConsulting\Attributes\Events\AttributesSynced;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Review 2026-09-28: sync/setMany/stage()->save() detached first and validated one value
 * at a time, so one invalid value left a half-applied write — with `forceDelete` the
 * removed rows were gone for good. Everything is now validated before the first write,
 * and the writes run in one transaction.
 */
beforeEach(function (): void {
    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer, rules: ['min:1', 'max:5']));
});

/**
 * @return array<string, mixed>
 */
function atomicSnapshot(Product $product): array
{
    return Attribute::withTrashed()
        ->where('owner_type', $product->getMorphClass())
        ->where('owner_id', $product->getKey())
        ->orderBy('name')
        ->get()
        ->mapWithKeys(fn (Attribute $attribute): array => [
            $attribute->name => [$attribute->value, $attribute->trashed()],
        ])
        ->all();
}

it('leaves the owner untouched when a sync item is invalid', function (): void {
    $product = Product::query()->create();
    $product->attachAttributes(['color' => 'red', 'size' => 'L', 'rating' => 3]);
    $before = atomicSnapshot($product);

    expect(fn () => $product->syncAttributes(['color' => 'blue', 'rating' => 9], true))
        ->toThrow(InvalidAttributeValueException::class);

    expect(atomicSnapshot($product))->toBe($before);
});

it('leaves the owner untouched when a setMany item is invalid', function (): void {
    $product = Product::query()->create();

    expect(fn () => $product->attachAttributes(['a' => 1, 'rating' => 99, 'b' => 2]))
        ->toThrow(InvalidAttributeValueException::class);

    expect(atomicSnapshot($product))->toBe([]);
});

it('leaves the owner untouched when a staged save has an invalid item', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    expect(fn () => Attributes::for($product)->stage()->set('color', 'blue')->set('rating', 0)->save())
        ->toThrow(InvalidAttributeValueException::class);

    expect(atomicSnapshot($product))->toBe(['color' => ['red', false]]);
});

it('rolls back every write of a sync when a later write fails', function (): void {
    $product = Product::query()->create();
    $product->attachAttributes(['color' => 'red', 'size' => 'L', 'rating' => 3]);
    $before = atomicSnapshot($product);

    // A failure only the database can raise (validation already passed for every item).
    Attribute::saving(function (Attribute $attribute): void {
        if ($attribute->name === 'rating') {
            throw new RuntimeException('disk full');
        }
    });

    expect(fn () => $product->syncAttributes(['color' => 'blue', 'rating' => 4], true))
        ->toThrow(RuntimeException::class, 'disk full');

    expect(atomicSnapshot($product))->toBe($before);
});

it('fires no attribute events for a rolled-back write', function (): void {
    $product = Product::query()->create();
    $product->attachAttributes(['color' => 'red', 'size' => 'L']);

    $fired = [];
    Event::listen([AttributeAttached::class, AttributeDetached::class, AttributesSynced::class], function (object $event) use (&$fired): void {
        $fired[] = $event::class;
    });

    Attribute::saving(function (Attribute $attribute): void {
        if ($attribute->name === 'rating') {
            throw new RuntimeException('disk full');
        }
    });

    expect(fn () => $product->syncAttributes(['color' => 'blue', 'rating' => 4]))->toThrow(RuntimeException::class);

    expect($fired)->toBe([]);
});

it('fires the events of a committed sync', function (): void {
    $product = Product::query()->create();
    $product->attachAttributes(['color' => 'red', 'size' => 'L']);

    $fired = [];
    Event::listen([AttributeAttached::class, AttributeDetached::class, AttributesSynced::class], function (object $event) use (&$fired): void {
        $fired[] = class_basename($event);
    });

    $product->syncAttributes(['color' => 'blue']);

    expect($fired)->toBe(['AttributeDetached', 'AttributeAttached', 'AttributesSynced']);
});
