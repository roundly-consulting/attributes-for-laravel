<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Support\StoredAttributeValue;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('exposes the raw underlying value', function (): void {
    expect((new StoredAttributeValue(42))->raw())->toBe(42);
});

it('coerces to int', function (): void {
    expect((new StoredAttributeValue('5'))->int())->toBe(5)
        ->and((new StoredAttributeValue(null))->int())->toBeNull();
});

it('coerces to float', function (): void {
    expect((new StoredAttributeValue('1.5'))->float())->toBe(1.5)
        ->and((new StoredAttributeValue(null))->float())->toBeNull();
});

it('coerces to bool', function (): void {
    expect((new StoredAttributeValue(1))->bool())->toBeTrue()
        ->and((new StoredAttributeValue(0))->bool())->toBeFalse()
        ->and((new StoredAttributeValue(null))->bool())->toBeNull();
});

it('coerces to string', function (): void {
    expect((new StoredAttributeValue('white'))->string())->toBe('white')
        ->and((new StoredAttributeValue(['a' => 1]))->string())->toBe('{"a":1}')
        ->and((new StoredAttributeValue(null))->string())->toBeNull();
});

it('coerces to array', function (): void {
    expect((new StoredAttributeValue(['a', 'b']))->array())->toBe(['a', 'b'])
        ->and((new StoredAttributeValue('x'))->array())->toBe(['x'])
        ->and((new StoredAttributeValue(null))->array())->toBeNull();
});

it('coerces to a Carbon date', function (): void {
    $value = new StoredAttributeValue('2026-01-02T00:00:00+00:00');

    expect($value->date())->toBeInstanceOf(Carbon::class)
        ->and($value->date()?->toDateString())->toBe('2026-01-02')
        ->and((new StoredAttributeValue(null))->date())->toBeNull();
});

it('handles a DateTime instance for string and date', function (): void {
    $now = new DateTimeImmutable('2026-03-04T05:06:07+00:00');
    $value = new StoredAttributeValue($now);

    expect($value->date())->toBeInstanceOf(Carbon::class)
        ->and($value->string())->toContain('2026-03-04');
});

it('reports null and casts to string', function (): void {
    expect((new StoredAttributeValue(null))->isNull())->toBeTrue()
        ->and((string) new StoredAttributeValue(null))->toBe('')
        ->and((string) new StoredAttributeValue('hi'))->toBe('hi')
        ->and((new StoredAttributeValue('hi'))->isNull())->toBeFalse();
});

it('matches the typed reader methods for set and default values', function (): void {
    config()->set('attributes.definitions', [
        'retries' => ['type' => 'integer', 'default' => 3],
    ]);

    app()->forgetInstance(AttributeRegistry::class);
    $provider = new AttributesServiceProvider(app());
    $provider->register();
    $provider->boot();

    $product = Product::query()->create();
    $product->attachAttribute('age', 21);

    expect($product->attr('age')->int())->toBe($product->attributeInt('age'))
        ->and($product->attr('retries')->int())->toBe($product->attributeInt('retries'))
        ->and($product->attr('retries')->int())->toBe(3);
});
