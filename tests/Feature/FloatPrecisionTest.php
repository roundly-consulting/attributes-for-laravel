<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Review 2026-09-28: floats were stored through `(string) $float`, which keeps only
 * `precision` (14) significant digits.
 */
it('round-trips a float with full precision', function (float $value): void {
    $product = Product::query()->create();
    $product->attachAttribute('number', $value);

    expect($product->fresh()->getAttachedAttributeValue('number'))->toBe($value);
})->with([
    'pi' => M_PI,
    'binary fraction' => 0.1 + 0.2,
    'large' => 1.0E+25,
    'short' => 9.99,
    'negative' => -273.15,
]);

/**
 * Chat review C-16: arrays were JSON-encoded without JSON_PRESERVE_ZERO_FRACTION, so a
 * whole-number float inside an array (`[2.0]`) came back as an integer (`[2]`).
 */
it('keeps whole-number floats inside an array as floats', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('weights', [2.0, 1.5, 3]);

    expect(Attribute::query()->where('name', 'weights')->value('value'))->toBe('[2.0,1.5,3]')
        ->and($product->fresh()->getAttachedAttributeValue('weights'))->toBe([2.0, 1.5, 3]);
});

/**
 * The documented caveat of the fix above: an array stored before it kept no zero fraction
 * (`[2]`). Equality compares the stored JSON text, so such a row is matched by the integer
 * form of the array, not by `[2.0]`.
 */
it('matches an array stored without the zero fraction by its integer form', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('weights', [2]);

    expect(Product::query()->whereAttribute('weights', [2])->count())->toBe(1)
        ->and(Product::query()->whereAttribute('weights', [2.0])->count())->toBe(0);
});
