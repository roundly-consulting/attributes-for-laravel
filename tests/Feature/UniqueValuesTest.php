<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\DefinedProduct;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Review 2026-09-28: `unique` compared the plaintext storage string against the
 * non-deterministic ciphertext of encrypted values, so it never fired for them, and the
 * whole check was a read followed by a write with no database guarantee behind it.
 * Unique values now carry a deterministic `unique_hash` (keyed for encrypted values)
 * with a unique index on it.
 */
it('rejects a duplicate encrypted unique value', function (UniqueScope $scope): void {
    Attributes::define(new AttributeDefinitionData('api_key', AttributeType::String_, unique: $scope, encrypted: true));

    Product::query()->create()->attachAttribute('api_key', 'SAME');

    expect(fn () => Product::query()->create()->attachAttribute('api_key', 'SAME'))
        ->toThrow(DuplicateAttributeValueException::class);
})->with([
    'global' => UniqueScope::Global_,
    'owner' => UniqueScope::Owner,
]);

it('allows the same owner to re-save its encrypted unique value', function (): void {
    Attributes::define(new AttributeDefinitionData('api_key', AttributeType::String_, unique: UniqueScope::Global_, encrypted: true));

    $product = Product::query()->create();
    $product->attachAttribute('api_key', 'SAME');
    $product->attachAttribute('api_key', 'SAME');

    expect($product->getAttachedAttributeValue('api_key'))->toBe('SAME');
});

it('lets an owner of another type reuse an owner-scoped encrypted value', function (): void {
    Attributes::define(new AttributeDefinitionData('api_key', AttributeType::String_, unique: UniqueScope::Owner, encrypted: true));

    Product::query()->create()->attachAttribute('api_key', 'SAME');
    $other = DefinedProduct::query()->create();
    $other->attachAttribute('api_key', 'SAME');

    expect($other->getAttachedAttributeValue('api_key'))->toBe('SAME');
});

it('never stores the plaintext of an encrypted unique value in its index', function (): void {
    Attributes::define(new AttributeDefinitionData('api_key', AttributeType::String_, unique: UniqueScope::Global_, encrypted: true));
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    $product = Product::query()->create();
    $product->attachAttributes(['api_key' => 'SAME', 'sku' => 'SAME']);

    $hashes = Attribute::query()->pluck('unique_hash', 'name');

    expect($hashes['api_key'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($hashes['sku'])->toMatch('/^[0-9a-f]{64}$/')
        // The keyed (encrypted) index differs from the plain digest of the same value.
        ->and($hashes['api_key'])->not->toBe($hashes['sku'])
        ->and($hashes['api_key'])->not->toContain('SAME');
});

it('stores no unique hash for a value without a unique definition', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    expect(Attribute::query()->value('unique_hash'))->toBeNull();
});

it('frees a unique value once its attribute is detached', function (): void {
    Attributes::define(new AttributeDefinitionData('api_key', AttributeType::String_, unique: UniqueScope::Global_, encrypted: true));

    $first = Product::query()->create();
    $first->attachAttribute('api_key', 'SAME');
    $first->detachAttribute('api_key');

    $second = Product::query()->create();
    $second->attachAttribute('api_key', 'SAME');

    expect($second->getAttachedAttributeValue('api_key'))->toBe('SAME')
        ->and(Attribute::onlyTrashed()->value('unique_hash'))->toBeNull();
});

it('frees a unique value when a host soft-deletes the attribute model directly', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    $first = Product::query()->create();
    $first->attachAttribute('sku', 'ABC');
    $first->getAttachedAttribute('sku')?->delete();

    $second = Product::query()->create();
    $second->attachAttribute('sku', 'ABC');

    expect($second->getAttachedAttributeValue('sku'))->toBe('ABC');
});

it('enforces unique values at the database level', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    Product::query()->create()->attachAttribute('sku', 'ABC');
    $row = (array) DB::table('attributes')->first();

    unset($row['id']);
    $row['owner_id'] = Product::query()->create()->getKey();

    expect(fn () => DB::table('attributes')->insert($row))->toThrow(UniqueConstraintViolationException::class);
});

it('turns a lost unique-value race into DuplicateAttributeValueException', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    // Learn the competitor's row, then remove it so our pre-check finds nothing…
    $competitor = Product::query()->create();
    $competitor->attachAttribute('sku', 'ABC');
    $row = (array) DB::table('attributes')->first();
    DB::table('attributes')->delete();

    // …and let it commit right after that pre-check ran — the check-then-write window.
    $raced = false;
    DB::listen(function (QueryExecuted $query) use (&$raced, $row): void {
        if ($raced || ! str_contains($query->sql, 'attributes')) {
            return;
        }

        $raced = true;
        DB::table('attributes')->insert($row);
    });

    $product = Product::query()->create();

    expect(fn () => $product->attachAttribute('sku', 'ABC'))->toThrow(DuplicateAttributeValueException::class)
        ->and($raced)->toBeTrue()
        ->and($product->hasAttachedAttribute('sku'))->toBeFalse();
});

it('turns a unique-value race lost on an update into DuplicateAttributeValueException', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

    // The competitor's row for NEW, learned and then removed…
    $competitor = Product::query()->create();
    $competitor->attachAttribute('sku', 'NEW');
    $row = (array) DB::table('attributes')->first();
    DB::table('attributes')->delete();

    $product = Product::query()->create();
    $product->attachAttribute('sku', 'OLD');

    // …commits right after our pre-check for NEW.
    $raced = false;
    DB::listen(function (QueryExecuted $query) use (&$raced, $row): void {
        if ($raced || ! str_contains($query->sql, 'attributes')) {
            return;
        }

        $raced = true;
        DB::table('attributes')->insert($row);
    });

    expect(fn () => $product->attachAttribute('sku', 'NEW'))->toThrow(DuplicateAttributeValueException::class)
        ->and($raced)->toBeTrue()
        ->and($product->getAttachedAttributeValue('sku'))->toBe('OLD');
});

it('names the attribute when a unique pre-check gets a value its type cannot take', function (): void {
    Attributes::define(new AttributeDefinitionData('code', AttributeType::Integer, unique: UniqueScope::Global_));

    expect(fn () => Attributes::assertUnique(Product::query()->create(), 'code', 'many'))
        ->toThrow(InvalidAttributeValueException::class, 'Attribute [code] has an invalid value');
});
