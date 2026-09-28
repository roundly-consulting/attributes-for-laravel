<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Review 2026-09-28: `attributeDate()` handed the cast's CarbonImmutable to an
 * `is_scalar()` guard, fell through to `Carbon::parse(null)` and returned "now" for
 * every stored datetime.
 */
it('reads a stored datetime back through attributeDate()', function (): void {
    Carbon::setTestNow('2026-09-28 15:44:04');

    $product = Product::query()->create();
    $product->attachAttribute('published_at', Carbon::parse('2020-01-02 03:04:05', 'UTC'));

    expect($product->attributeDate('published_at')?->toIso8601String())->toBe('2020-01-02T03:04:05+00:00')
        ->and($product->attr('published_at')->date()?->toIso8601String())->toBe('2020-01-02T03:04:05+00:00');

    Carbon::setTestNow();
});

it('returns null from attributeDate() and attr()->date() for a value that is not a date', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('tags', ['a', 'b']);

    expect($product->attributeDate('tags'))->toBeNull()
        ->and($product->attr('tags')->date())->toBeNull();
});

/**
 * Review 2026-09-28: an explicitly stored `null` read back as the definition default,
 * while `hasAttachedAttribute()` said the attribute was there. Defaults are for unset
 * attributes only.
 */
it('keeps an explicitly stored null instead of the default', function (): void {
    Attributes::define(new AttributeDefinitionData('retries', AttributeType::Integer, default: 3));

    $product = Product::query()->create();
    $product->attachAttribute('retries', null);

    expect($product->hasAttachedAttribute('retries'))->toBeTrue()
        ->and($product->getAttachedAttributeValue('retries'))->toBeNull()
        ->and($product->attr('retries')->int())->toBeNull()
        ->and(Attributes::for($product)->get('retries'))->toBeNull()
        ->and(Product::query()->create()->attributeInt('retries'))->toBe(3);
});
