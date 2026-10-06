<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\DefinedProduct;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Chat review C-7: the value cast resolves the definition (type, encryption, unique
 * hash) from the name and owner already on the model. Eloquent's relation `create()`
 * and the factory set the owner after the value — and an attributes array may put
 * `value` before `name` — so the cast saw no definition and stored an encrypted
 * attribute in plaintext. It is re-applied before saving once the context is known.
 */
function storedRow(string $name): object
{
    return DB::table('attributes')->where('name', $name)->first();
}

it('encrypts a model-declared encrypted value created through the relation', function (): void {
    $product = DefinedProduct::query()->create();

    $product->attachedAttributes()->create(['name' => 'token', 'value' => 's3cret']);

    $row = storedRow('token');

    expect((bool) $row->is_encrypted)->toBeTrue()
        ->and($row->value)->not->toBe('s3cret')
        ->and(Crypt::decryptString($row->value))->toBe('s3cret')
        ->and($product->getAttachedAttributeValue('token'))->toBe('s3cret');
});

it('encrypts a global encrypted value given before its name', function (): void {
    Attributes::define(new AttributeDefinitionData('pin', AttributeType::String_, encrypted: true));
    $product = Product::query()->create();

    $product->attachedAttributes()->create(['value' => '1234', 'name' => 'pin']);

    expect((bool) storedRow('pin')->is_encrypted)->toBeTrue()
        ->and($product->getAttachedAttributeValue('pin'))->toBe('1234');
});

it('encrypts a model-declared encrypted value made by the factory', function (): void {
    $product = DefinedProduct::query()->create();

    Attribute::factory()->create([
        'name' => 'token',
        'value' => 'fact',
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
    ]);

    expect((bool) storedRow('token')->is_encrypted)->toBeTrue()
        ->and($product->getAttachedAttributeValue('token'))->toBe('fact');
});

it('stores a model-declared typed value created through the relation in its type', function (): void {
    $product = DefinedProduct::query()->create();

    $product->attachedAttributes()->create(['name' => 'retries', 'value' => '7']);

    expect(storedRow('retries')->value_type)->toBe('integer')
        ->and($product->getAttachedAttributeValue('retries'))->toBe(7);
});

it('hashes an owner-scoped unique value created through the relation with its owner type', function (): void {
    Attributes::define(new AttributeDefinitionData('code', AttributeType::String_, unique: UniqueScope::Owner));

    Product::query()->create()->attachedAttributes()->create(['name' => 'code', 'value' => 'C1']);

    expect(fn () => Product::query()->create()->attachAttribute('code', 'C1'))
        ->toThrow(DuplicateAttributeValueException::class);
});

it('keeps an explicit value_type the caller set after the value', function (): void {
    $product = Product::query()->create();

    $product->attachedAttributes()->create(['name' => 'raw', 'value' => '5', 'value_type' => 'integer']);

    expect($product->getAttachedAttributeValue('raw'))->toBe(5);
});
