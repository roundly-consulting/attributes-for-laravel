<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\MissingRequiredAttributeException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\DefinedProduct;
use RoundlyConsulting\Attributes\Tests\Models\MethodProduct;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('enforces a model property definition on validation', function (): void {
    DefinedProduct::query()->create()->attachAttribute('rating', 99);
})->throws(InvalidAttributeValueException::class);

it('applies a model property default on typed reads', function (): void {
    $product = DefinedProduct::query()->create();

    expect($product->attributeInt('retries'))->toBe(3)
        ->and($product->attr('retries')->int())->toBe(3);
});

it('encrypts a value declared encrypted on the model', function (): void {
    $product = DefinedProduct::query()->create();
    $product->attachAttribute('token', 'sekret');

    $row = DB::table('attributes')->where('name', 'token')->first();

    expect($row->value)->not->toBe('sekret')
        ->and((int) $row->is_encrypted)->toBe(1)
        ->and($product->fresh()->getAttachedAttributeValue('token'))->toBe('sekret');
});

it('throws for a missing required model attribute', function (): void {
    DefinedProduct::query()->create()->validateAttributes();
})->throws(MissingRequiredAttributeException::class);

it('enforces a model method definition', function (): void {
    MethodProduct::query()->create()->attachAttribute('rating', 50);
})->throws(InvalidAttributeValueException::class);

it('reads a model method default', function (): void {
    expect(MethodProduct::query()->create()->attributeInt('level'))->toBe(7);
});

it('overrides a global definition for that model only', function (): void {
    Attributes::define(new AttributeDefinitionData(
        'retries',
        AttributeType::Integer,
        default: 99,
    ));

    $defined = DefinedProduct::query()->create();
    $plain = Product::query()->create();

    expect($defined->attributeInt('retries'))->toBe(3)
        ->and($plain->attributeInt('retries'))->toBe(99);
});

it('does not let two models interfere', function (): void {
    $defined = DefinedProduct::query()->create();
    $method = MethodProduct::query()->create();

    expect($defined->attributeInt('retries'))->toBe(3)
        ->and($defined->attributeInt('level'))->toBeNull()
        ->and($method->attributeInt('level'))->toBe(7)
        ->and($method->attributeInt('retries'))->toBeNull();
});
