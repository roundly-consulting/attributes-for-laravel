<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('attaches single attribute to model', function () {
    $product = createProduct();

    $product->attachAttribute('color', 'white');

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'white',
    ]);
});

/**
 * The meta payload is pinned twice, and each catches something the other cannot: the
 * database comparison proves the stored column, the model read proves the value survives
 * the `collection` cast round-trip.
 *
 * `meta` is `jsonb`, so `meta = ?` runs on every engine. It could not while the column was
 * `json`: Postgres ships **no equality operator for `json`** (only `jsonb` has one), so the
 * comparison died with `operator does not exist: json = unknown` however the value was
 * cast, and sqlite compared the column as text and never noticed.
 */
it('syncs attribute meta data', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');

    $product->syncAttributeMeta('color', collect([
        'is_pretty' => 'yes',
    ]));

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'white',
        'meta' => $this->castAsJson(['is_pretty' => 'yes']),
    ]);

    expect($product->getAttachedAttributeMeta('color')->all())->toBe(['is_pretty' => 'yes']);
});

it('attaches single attribute with meta data to model', function () {
    $product = createProduct();

    $product->attachAttribute('color', 'white', collect([
        'is_unique' => 'yes',
    ]));

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'white',
        'meta' => $this->castAsJson(['is_unique' => 'yes']),
    ]);

    // Also read back through the model: that is what proves the `collection` cast
    // round-trips, which the column comparison above never touches.
    expect($product->getAttachedAttributeMeta('color')->all())->toBe(['is_unique' => 'yes']);
});

it('checks whether attribute is attached to model - no eager loading', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');

    DB::enableQueryLog();

    expect($product->hasAttachedAttribute('color'))
        ->toBeTrue()
        ->and($product->hasAttachedAttribute('not-existing'))
        ->toBeFalse()
        ->and(DB::getQueryLog())->toHaveCount(2);

    DB::disableQueryLog();
});

it('checks whether attribute is attached to model - with eager loading', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');
    $product->load('attachedAttributes');

    DB::enableQueryLog();

    expect($product->hasAttachedAttribute('color'))
        ->toBeTrue()
        ->and($product->hasAttachedAttribute('not-existing'))
        ->toBeFalse()
        ->and(DB::getQueryLog())->toHaveCount(0);

    DB::disableQueryLog();
});

it('returns attached attributes as collection - no eager loading', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');

    DB::enableQueryLog();

    expect($product->getAttachedAttributes())
        ->toBeInstanceOf(Collection::class)
        ->toArray()->toBe([
            'color' => 'white',
        ])
        ->and(DB::getQueryLog())->toHaveCount(1);

    DB::disableQueryLog();
});

it('returns attached attributes as collection - eager loading', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');
    $product->load('attachedAttributes');

    DB::enableQueryLog();

    expect($product->getAttachedAttributes())
        ->toBeInstanceOf(Collection::class)
        ->toArray()->toBe([
            'color' => 'white',
        ])
        ->and(DB::getQueryLog())->toHaveCount(0);

    DB::disableQueryLog();
});

it('attaches multiple attributes to model', function () {
    $product = createProduct();

    $product->attachAttributes([
        'color' => 'black',
        'size' => 'small',
    ]);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'small',
    ]);
});

it('detaches single attribute from product', function () {
    $product = createProduct();

    $product->attachAttribute('color', 'black');

    $product->detachAttribute('color');

    $this->assertSoftDeleted('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $product->detachAttribute('color', true);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);
});

it('detaches multiple attributes from product', function () {
    $product = createProduct();

    $product->attachAttributes([
        'color' => 'black',
        'size' => 'small',
    ]);

    $product->detachAttributes(['color', 'size']);

    $this->assertSoftDeleted('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertSoftDeleted('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'small',
    ]);

    $product->detachAttributes(['color', 'size'], true);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'small',
    ]);
});

it('sync attributes to product', function () {
    $product = createProduct();

    $product->syncAttributes([
        'color' => 'black',
        'size' => 'small',
    ]);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'small',
    ]);

    $product->syncAttributes([
        'size' => 'large',
    ]);

    $this->assertSoftDeleted('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'large',
    ]);

    $product->syncAttributes([
        'size' => 'medium',
    ], true);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'medium',
    ]);
});

it('destroys attributes except some from product', function () {
    $product = createProduct();

    $product->attachAttributes([
        'color' => 'black',
        'size' => 'small',
        'price' => 'high',
    ]);

    $product->destroyAttributesExcept(['color', 'size']);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'small',
    ]);

    $this->assertSoftDeleted('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'price',
        'value' => 'high',
    ]);

    $product->destroyAttributesExcept(['color'], true);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'small',
    ]);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'price',
        'value' => 'high',
    ]);
});

it('destroys specific attributes from product', function () {
    $product = createProduct();

    $product->attachAttributes([
        'color' => 'black',
        'size' => 'small',
        'price' => 'high',
    ]);

    $product->destroyAttributes(['price']);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'small',
    ]);

    $this->assertSoftDeleted('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'price',
        'value' => 'high',
    ]);

    $product->destroyAttributes(['size', 'price'], true);

    $this->assertDatabaseHas('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'color',
        'value' => 'black',
    ]);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'size',
        'value' => 'small',
    ]);

    $this->assertDatabaseMissing('attributes', [
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
        'name' => 'price',
        'value' => 'high',
    ]);
});

it('has relationship to owner of attribute via relationship', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');

    /** @var Attribute $attribute */
    $attribute = $product->attachedAttributes()->first();

    expect($attribute)
        ->owner()->toBeInstanceOf(MorphTo::class)
        ->owner->is($product)->toBeTrue();
});

it('returns attribute model from database - no eager loading', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');

    DB::enableQueryLog();

    $attribute = $product->getAttachedAttribute('color');
    $product->getAttachedAttribute('size'); // Just to get 2 queries

    expect($attribute)
        ->toBeInstanceOf(Attribute::class)
        ->and(DB::getQueryLog())->toHaveCount(2);

    DB::disableQueryLog();
});

it('returns attribute model from already loaded relationship', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');
    $product->load('attachedAttributes');

    DB::enableQueryLog();

    $attribute = $product->getAttachedAttribute('color');
    $product->getAttachedAttribute('size'); // Just to test we are using eager loaded stuff

    expect($attribute)
        ->toBeInstanceOf(Attribute::class)
        ->and(DB::getQueryLog())->toHaveCount(0);

    DB::disableQueryLog();
});

it('returns attribute value', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white');

    expect($product->getAttachedAttributeValue('color'))->toBe('white');
});

it('returns attribute meta data', function () {
    $product = createProduct();
    $product->attachAttribute('color', 'white', collect([
        'is_unique' => 'yes',
    ]));

    expect($product->getAttachedAttributeMeta('color'))
        ->toBeInstanceOf(Collection::class)
        ->toArray()->toBe([
            'is_unique' => 'yes',
        ]);
});

it('preserves value types on attach and read', function (): void {
    $product = createProduct();

    $product->attachAttribute('rating', 5);
    $product->attachAttribute('on_sale', true);
    $product->attachAttribute('price', 9.99);
    $product->attachAttribute('tags', ['a', 'b']);

    expect($product->getAttachedAttributeValue('rating'))->toBe(5)
        ->and($product->getAttachedAttributeValue('on_sale'))->toBeTrue()
        ->and($product->getAttachedAttributeValue('price'))->toBe(9.99)
        ->and($product->getAttachedAttributeValue('tags'))->toBe(['a', 'b']);
});

it('exposes typed convenience readers', function (): void {
    $product = createProduct();

    $product->attachAttribute('rating', 5);
    $product->attachAttribute('on_sale', true);
    $product->attachAttribute('price', 9.5);
    $product->attachAttribute('tags', ['a']);
    $product->attachAttribute('published_at', '2026-06-19');

    expect($product->attributeInt('rating'))->toBe(5)
        ->and($product->attributeBool('on_sale'))->toBeTrue()
        ->and($product->attributeFloat('price'))->toBe(9.5)
        ->and($product->attributeArray('tags'))->toBe(['a'])
        ->and($product->attributeDate('published_at')->toDateString())->toBe('2026-06-19');
});

it('returns null from typed readers for missing attributes', function (): void {
    $product = createProduct();

    expect($product->attributeInt('missing'))->toBeNull()
        ->and($product->attributeBool('missing'))->toBeNull()
        ->and($product->attributeFloat('missing'))->toBeNull()
        ->and($product->attributeArray('missing'))->toBeNull()
        ->and($product->attributeDate('missing'))->toBeNull()
        ->and($product->getAttachedAttributeValueAsString('missing'))->toBeNull();
});

it('wraps a scalar value as an array reader', function (): void {
    $product = createProduct();
    $product->attachAttribute('color', 'white');

    expect($product->attributeArray('color'))->toBe(['white']);
});

it('returns the stored string form of a typed value', function (): void {
    $product = createProduct();
    $product->attachAttribute('rating', 5);

    expect($product->getAttachedAttributeValueAsString('rating'))->toBe('5');
});

it('returns typed values in the attached attributes map', function (): void {
    $product = createProduct();
    $product->attachAttribute('color', 'white');
    $product->attachAttribute('rating', 5);

    expect($product->getAttachedAttributes()->toArray())->toBe([
        'color' => 'white',
        'rating' => 5,
    ]);
});

if (! function_exists('createProduct')) {
    function createProduct(): Product
    {
        /** @var Product $product */
        $product = Product::create();

        return $product;
    }
}
