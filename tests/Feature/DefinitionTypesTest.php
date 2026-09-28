<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\DefinedProduct;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Review 2026-09-28: the stored type was inferred from the PHP value, never taken from
 * the definition — an integer definition fed `'5'` from a request stored (and read back)
 * the string `'5'`.
 */
it('stores a request string as the defined integer type', function (): void {
    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer, rules: ['min:1', 'max:5']));

    $product = Product::query()->create();
    $product->attachAttribute('rating', '5');

    expect($product->getAttachedAttribute('rating')?->type())->toBe(AttributeType::Integer)
        ->and($product->fresh()->getAttachedAttributeValue('rating'))->toBe(5);
});

it('stores "0" as false for a boolean definition', function (): void {
    Attributes::define(new AttributeDefinitionData('on_sale', AttributeType::Boolean));

    $product = Product::query()->create();
    $product->attachAttribute('on_sale', '0');

    expect($product->getAttachedAttribute('on_sale')?->type())->toBe(AttributeType::Boolean)
        ->and($product->fresh()->getAttachedAttributeValue('on_sale'))->toBeFalse()
        ->and($product->attr('on_sale')->bool())->toBeFalse();
});

it('stores a date string as a datetime for a datetime definition', function (): void {
    Attributes::define(new AttributeDefinitionData('starts_at', AttributeType::DateTime));

    $product = Product::query()->create();
    $product->attachAttribute('starts_at', '2026-01-01');

    $stored = Attribute::query()->where('name', 'starts_at')->firstOrFail();

    expect($stored->type())->toBe(AttributeType::DateTime)
        ->and($stored->getRawOriginal('value'))->toBe('2026-01-01T00:00:00+00:00')
        ->and($product->attr('starts_at')->date()?->equalTo(Carbon::parse('2026-01-01', 'UTC')))->toBeTrue();
});

it('stores a numeric string as the defined float type', function (): void {
    Attributes::define(new AttributeDefinitionData('price', AttributeType::Float_));

    $product = Product::query()->create();
    $product->attachAttribute('price', '9.99');

    expect($product->fresh()->getAttachedAttributeValue('price'))->toBe(9.99);
});

it('stores an integral float and a boolean under an integer definition', function (): void {
    Attributes::define(new AttributeDefinitionData('count', AttributeType::Integer));
    Attributes::define(new AttributeDefinitionData('flag_count', AttributeType::Integer));

    $product = Product::query()->create();
    $product->attachAttributes(['count' => 5.0, 'flag_count' => true]);

    expect($product->fresh()->getAttachedAttributeValue('count'))->toBe(5)
        ->and($product->fresh()->getAttachedAttributeValue('flag_count'))->toBe(1);
});

it('applies a model-declared type', function (): void {
    $product = DefinedProduct::query()->create();
    $product->attachAttribute('retries', '7');

    expect($product->fresh()->getAttachedAttributeValue('retries'))->toBe(7);
});

it('names the attribute when a value cannot take its defined type', function (): void {
    Attributes::define(new AttributeDefinitionData('count', AttributeType::Integer));

    $product = Product::query()->create();

    // A direct model write skips the validator — the cast still refuses the value.
    expect(fn () => $product->attachedAttributes()->create(['name' => 'count', 'value' => 'many']))
        ->toThrow(InvalidAttributeValueException::class, 'Attribute [count] has an invalid value');
});

it('keeps inferring the type of an undefined attribute', function (): void {
    $product = Product::query()->create();
    $product->attachAttributes(['code' => '5', 'count' => 5]);

    expect($product->fresh()->getAttachedAttributeValue('code'))->toBe('5')
        ->and($product->fresh()->getAttachedAttributeValue('count'))->toBe(5);
});
