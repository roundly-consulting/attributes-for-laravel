<?php

declare(strict_types=1);

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
