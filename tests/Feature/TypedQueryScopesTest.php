<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * @param  list<int|float>  $scores
 * @return array<array-key, int> score => product id
 */
function scoredProducts(array $scores): array
{
    $ids = [];

    foreach ($scores as $score) {
        $product = Product::query()->create();
        $product->attachAttribute('score', $score);
        $ids[(string) $score] = $product->getKey();
    }

    return $ids;
}

/**
 * Review 2026-09-28: orderByAttribute sorted the text column, so [9, 10, 2] came back as
 * 9, 2, 10.
 */
it('orders numeric attributes numerically', function (): void {
    $ids = scoredProducts([9, 10, 2, 2.5]);

    expect(Product::query()->orderByAttribute('score', 'desc')->pluck('id')->all())
        ->toBe([$ids[10], $ids[9], $ids['2.5'], $ids[2]])
        ->and(Product::query()->orderByAttribute('score')->pluck('id')->all())
        ->toBe([$ids[2], $ids['2.5'], $ids[9], $ids[10]]);
});

it('still orders string attributes by their text', function (): void {
    $pear = Product::query()->create();
    $apple = Product::query()->create();
    $pear->attachAttribute('fruit', 'pear');
    $apple->attachAttribute('fruit', 'apple');

    expect(Product::query()->orderByAttribute('fruit')->pluck('id')->all())->toBe([$apple->getKey(), $pear->getKey()]);
});

it('filters numeric ranges numerically', function (): void {
    $ids = scoredProducts([9, 10, 2]);

    expect(Product::query()->whereAttributeBetween('score', 2, 9)->orderBy('id')->pluck('id')->all())
        ->toBe([$ids[9], $ids[2]])
        ->and(Product::query()->whereAttributeBetween('score', 5, 20)->orderBy('id')->pluck('id')->all())
        ->toBe([$ids[9], $ids[10]])
        ->and(Product::query()->whereAttributeBetween('score', 9.5, 10.0)->pluck('id')->all())
        ->toBe([$ids[10]]);
});

it('filters a defined numeric range with request strings', function (): void {
    Attributes::define(new AttributeDefinitionData('score', AttributeType::Integer));
    $ids = scoredProducts([9, 10, 2]);

    expect(Product::query()->whereAttributeBetween('score', '3', '10')->orderBy('id')->pluck('id')->all())
        ->toBe([$ids[9], $ids[10]]);
});

it('never casts an encrypted numeric value', function (): void {
    Attributes::define(new AttributeDefinitionData('pin', AttributeType::Integer, encrypted: true));

    $product = Product::query()->create();
    $product->attachAttribute('pin', 1234);

    expect(Product::query()->orderByAttribute('pin')->pluck('id')->all())->toBe([$product->getKey()])
        ->and(Product::query()->whereAttributeBetween('pin', 0, 9999)->pluck('id')->all())->toBe([]);
});

/**
 * Review 2026-09-28: `whereAttribute` compared only the storage string, so the integer 5
 * matched a stored string '5'.
 */
it('matches whereAttribute by type', function (): void {
    $string = Product::query()->create();
    $string->attachAttribute('code', '5');
    $int = Product::query()->create();
    $int->attachAttribute('code', 5);

    expect(Product::query()->whereAttribute('code', 5)->pluck('id')->all())->toBe([$int->getKey()])
        ->and(Product::query()->whereAttribute('code', '5')->pluck('id')->all())->toBe([$string->getKey()]);
});

it('treats integers and floats as one numeric family for equality', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('weight', 10.0);

    expect(Product::query()->whereAttribute('weight', 10)->pluck('id')->all())->toBe([$product->getKey()]);
});

it('coerces a needle to the defined type', function (): void {
    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer));

    $product = Product::query()->create();
    $product->attachAttribute('rating', 5);

    expect(Product::query()->whereAttribute('rating', '5')->pluck('id')->all())->toBe([$product->getKey()])
        // A needle the defined type cannot take matches nothing rather than throwing.
        ->and(Product::query()->whereAttribute('rating', 'five')->pluck('id')->all())->toBe([]);
});

it('matches whereAttributeIn by type', function (): void {
    $string = Product::query()->create();
    $string->attachAttribute('code', '5');
    $int = Product::query()->create();
    $int->attachAttribute('code', 5);
    $null = Product::query()->create();
    $null->attachAttribute('code', null);

    expect(Product::query()->whereAttributeIn('code', [5, 7])->pluck('id')->all())->toBe([$int->getKey()])
        ->and(Product::query()->whereAttributeIn('code', ['5', null])->orderBy('id')->pluck('id')->all())
        ->toBe([$string->getKey(), $null->getKey()]);
});

it('matches a null needle regardless of type', function (): void {
    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer));

    $product = Product::query()->create();
    $product->attachAttribute('rating', null);

    expect(Product::query()->whereAttribute('rating', null)->pluck('id')->all())->toBe([$product->getKey()]);
});

it('matches nothing for an empty whereAttributeIn list', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('code', '5');

    expect(Product::query()->whereAttributeIn('code', [])->count())->toBe(0);
});
