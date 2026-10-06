<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Models\Attribute;

it('builds an attribute via its factory', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
    ]);

    expect($attribute)
        ->toBeInstanceOf(Attribute::class)
        ->name->toBeString()
        ->value->toBeString();
});

it('casts the meta column to a collection', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'meta' => ['featured' => true],
    ]);

    expect($attribute->meta)
        ->toBeInstanceOf(Collection::class)
        ->toArray()->toBe(['featured' => true]);
});

it('exposes typed value states from the factory', function (): void {
    $integer = Attribute::factory()->integerValue(7)->create([
        'owner_type' => 'product',
        'owner_id' => 1,
    ]);

    $boolean = Attribute::factory()->booleanValue(true)->create([
        'owner_type' => 'product',
        'owner_id' => 2,
    ]);

    expect($integer->fresh()->value)->toBe(7)
        ->and($boolean->fresh()->value)->toBeTrue();
});

it('attaches meta via the factory state', function (): void {
    $attribute = Attribute::factory()->withMeta(['source' => 'admin'])->create([
        'owner_type' => 'product',
        'owner_id' => 1,
    ]);

    expect($attribute->meta?->get('source'))->toBe('admin');
});

it('falls back to the default type accessor for unknown value types', function (): void {
    $attribute = Attribute::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
    ]);

    $attribute->forceFill(['value_type' => 'bogus'])->saveQuietly();

    expect($attribute->fresh()->type())->toBe(AttributeType::String_);
});

it('filters by name, owner and type scopes', function (): void {
    Attribute::factory()->create(['owner_type' => 'product', 'owner_id' => 1, 'name' => 'color']);
    Attribute::factory()->integerValue(5)->create(['owner_type' => 'product', 'owner_id' => 1, 'name' => 'rating']);
    Attribute::factory()->create(['owner_type' => 'product', 'owner_id' => 2, 'name' => 'color']);

    expect(Attribute::query()->forName('color')->count())->toBe(2)
        ->and(Attribute::query()->ofType(AttributeType::Integer)->count())->toBe(1);
});

it('uses the configured table name', function (): void {
    config()->set('attributes.table', 'custom_attributes');

    expect(new Attribute()->getTable())->toBe('custom_attributes');

    config()->set('attributes.table', 'attributes');
});

/**
 * Chat review C-13: the model documented `@property-read AttributeType $type`, but
 * `type()` is a method, so Eloquent took `->type` for a relation and threw
 * LogicException. Every property the docblock promises has to read.
 */
it('reads every property its docblock documents', function (): void {
    $attribute = Attribute::factory()->create(['owner_type' => 'product', 'owner_id' => 1]);

    preg_match_all('/@property(?:-read)?\s+[^$\n]+\$(\w+)/', (string) (new ReflectionClass(Attribute::class))->getDocComment(), $matches);

    expect($matches[1])->toContain('value', 'value_type', 'meta');

    $read = [];

    foreach ($matches[1] as $property) {
        $attribute->{$property};
        $read[] = $property;
    }

    expect($read)->toBe($matches[1]);
});
