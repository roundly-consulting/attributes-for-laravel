<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Review 2026-09-28: datetimes were stored with their own UTC offset
 * (`2026-01-01T10:00:00+01:00`), so text comparisons missed the same instant written in
 * another offset. They are stored normalized to UTC now.
 */
it('stores datetimes normalized to UTC', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('starts_at', Carbon::parse('2026-01-01 10:00', 'Europe/Bratislava'));

    expect(Attribute::query()->where('name', 'starts_at')->value('value'))->toBe('2026-01-01T09:00:00+00:00');
});

it('finds a datetime between bounds expressed in another offset', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('starts_at', Carbon::parse('2026-01-01 10:00', 'Europe/Bratislava'));

    $ids = Product::query()
        ->whereAttributeBetween('starts_at', Carbon::parse('2026-01-01 08:30', 'UTC'), Carbon::parse('2026-01-01 09:30', 'UTC'))
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$product->getKey()]);
});

it('matches a datetime by the same instant in another offset', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('starts_at', Carbon::parse('2026-01-01 10:00', 'Europe/Bratislava'));

    expect(Product::query()->whereAttribute('starts_at', Carbon::parse('2026-01-01 09:00', 'UTC'))->pluck('id')->all())
        ->toBe([$product->getKey()])
        ->and(Product::query()->whereAttribute('starts_at', Carbon::parse('2026-01-01 04:00', 'America/New_York'))->pluck('id')->all())
        ->toBe([$product->getKey()]);
});

it('orders datetimes written in different offsets chronologically', function (): void {
    $early = Product::query()->create();
    $late = Product::query()->create();
    // 23:30 in New York is 04:30Z the next day; 01:00 in Bratislava is 00:00Z.
    $late->attachAttribute('starts_at', Carbon::parse('2026-01-01 23:30', 'America/New_York'));
    $early->attachAttribute('starts_at', Carbon::parse('2026-01-02 01:00', 'Europe/Bratislava'));

    expect(Product::query()->orderByAttribute('starts_at')->pluck('id')->all())
        ->toBe([$early->getKey(), $late->getKey()]);
});

it('reads a datetime back as the same instant in the app timezone', function (): void {
    $product = Product::query()->create();
    $written = Carbon::parse('2026-01-01 10:00', 'Europe/Bratislava');
    $product->attachAttribute('starts_at', $written);

    $read = $product->fresh()->getAttachedAttributeValue('starts_at');

    expect($read)->toBeInstanceOf(DateTimeInterface::class)
        ->and($read->getTimestamp())->toBe($written->getTimestamp())
        ->and($read->getTimezone()->getName())->toBe(date_default_timezone_get());
});
