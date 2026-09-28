<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Actions\AttachAttributeAction;
use RoundlyConsulting\Attributes\AttributesManager;
use RoundlyConsulting\Attributes\Builders\AttributeWriter;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Events\AttributeDetached;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\OwnerAttributes;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('resolves the manager behind the facade', function (): void {
    expect(Attributes::getFacadeRoot())->toBeInstanceOf(AttributesManager::class)
        ->and(Attributes::for(Product::create()))->toBeInstanceOf(OwnerAttributes::class);
});

it('runs the same API through an injected manager', function (): void {
    $manager = app(AttributesManager::class);
    $product = Product::create();

    $manager->for($product)->set('color', 'red');

    expect($manager)->toBe(Attributes::getFacadeRoot())
        ->and($manager->for($product)->get('color'))->toBe('red');
});

it('runs the same use case through the raw action', function (): void {
    $product = Product::create();

    app(AttachAttributeAction::class)->execute($product, new AttributeData('color', 'red'));

    expect(Attributes::for($product)->get('color'))->toBe('red');
});

it('sets one attribute with array or collection meta', function (): void {
    $product = Product::create();

    $attribute = Attributes::for($product)->set('color', 'red', meta: ['hex' => '#f00']);
    Attributes::for($product)->set('size', 'L', meta: collect(['unit' => 'eu']));
    Attributes::for($product)->set('note');

    expect($attribute)->toBeInstanceOf(Attribute::class)
        ->and(Attributes::for($product)->get('color'))->toBe('red')
        ->and($product->getAttachedAttributeMeta('color')?->get('hex'))->toBe('#f00')
        ->and($product->getAttachedAttributeMeta('size')?->get('unit'))->toBe('eu')
        ->and(Attributes::for($product)->has('note'))->toBeTrue();
});

it('validates what it sets against the definition', function (): void {
    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer));

    try {
        Attributes::for(Product::create())->set('rating', 'not-a-number');
    } finally {
        Attributes::forget('rating');
    }
})->throws(InvalidAttributeValueException::class);

it('sets many attributes with a per-name meta map, keeping others', function (): void {
    $product = Product::create();
    Attributes::for($product)->set('keep', 'me');

    $written = Attributes::for($product)->setMany(['color' => 'red', 'size' => 'L'], meta: ['color' => ['hex' => '#f00']]);

    expect($written)->toHaveCount(2)
        ->each->toBeInstanceOf(Attribute::class)
        ->and(Attributes::for($product)->toKeyValue())->toBe(['keep' => 'me', 'color' => 'red', 'size' => 'L'])
        ->and($product->getAttachedAttributeMeta('color')?->get('hex'))->toBe('#f00')
        ->and($product->getAttachedAttributeMeta('size'))->toBeNull();
});

it('syncs to exactly the given set', function (): void {
    $product = Product::create();
    Attributes::for($product)->setMany(['color' => 'red', 'size' => 'L']);

    $written = Attributes::for($product)->sync(['color' => 'blue'], meta: ['color' => ['hex' => '#00f']]);

    expect($written)->toHaveCount(1)
        ->and(Attributes::for($product)->toKeyValue())->toBe(['color' => 'blue'])
        ->and($product->getAttachedAttributeMeta('color')?->get('hex'))->toBe('#00f');

    $this->assertSoftDeleted('attributes', ['name' => 'size']);
});

it('force-deletes what a sync removes when asked', function (): void {
    $product = Product::create();
    Attributes::for($product)->setMany(['color' => 'red', 'size' => 'L']);

    Attributes::for($product)->sync(['color' => 'red'], forceDelete: true);

    $this->assertDatabaseMissing('attributes', ['name' => 'size']);
});

it('forgets one or many attributes, soft or hard', function (): void {
    $product = Product::create();
    Attributes::for($product)->setMany(['a' => 1, 'b' => 2, 'c' => 3]);

    expect(Attributes::for($product)->forget('a'))->toBe(1)
        ->and(Attributes::for($product)->forget(['b', 'c'], forceDelete: true))->toBe(2)
        ->and(Attributes::for($product)->keys())->toBe([]);

    $this->assertSoftDeleted('attributes', ['name' => 'a']);
    $this->assertDatabaseMissing('attributes', ['name' => 'b']);
});

it('forgets everything except the kept names', function (): void {
    $product = Product::create();
    Attributes::for($product)->setMany(['color' => 'red', 'size' => 'L', 'price' => 10]);

    Event::fake([AttributeDetached::class]);

    $detached = Attributes::for($product)->forgetExcept(['color']);

    expect($detached)->toBe(['size', 'price'])
        ->and(Attributes::for($product)->keys())->toBe(['color'])
        ->and(Attributes::for($product)->forgetExcept(['color']))->toBe([]);

    Event::assertDispatchedTimes(AttributeDetached::class, 2);
});

it('purges soft-deleted extras when forgetting except with force', function (): void {
    $product = Product::create();
    Attributes::for($product)->setMany(['color' => 'red', 'size' => 'L']);
    Attributes::for($product)->forget('size');

    Attributes::for($product)->forgetExcept(['color'], forceDelete: true);

    $this->assertDatabaseMissing('attributes', ['name' => 'size']);
    $this->assertDatabaseHas('attributes', ['name' => 'color']);
});

it('replaces an attribute meta, creating the attribute when absent', function (): void {
    $product = Product::create();
    Attributes::for($product)->set('color', 'red', meta: ['hex' => '#f00']);

    Attributes::for($product)->meta('color', ['hex' => '#ff0000']);
    $created = Attributes::for($product)->meta('size', collect(['unit' => 'eu']));
    Attributes::for($product)->meta('color', null);

    expect($product->getAttachedAttributeMeta('color'))->toBeNull()
        ->and(Attributes::for($product)->get('color'))->toBe('red')
        ->and($created->meta?->get('unit'))->toBe('eu')
        ->and(Attributes::for($product)->get('size'))->toBeNull();
});

it('reads the recorded history, optionally for one name', function (): void {
    config()->set('attributes.history.enabled', true);
    $product = Product::create();
    $other = Product::create();

    Attributes::for($product)->set('color', 'red');
    Attributes::for($product)->set('size', 'L');
    Attributes::for($product)->forget('color');
    Attributes::for($other)->set('color', 'blue');

    $all = Attributes::for($product)->history();
    $color = Attributes::for($product)->history('color');

    expect($all)->toBeInstanceOf(Collection::class)->toHaveCount(3)
        ->and($all->first()?->type)->toBe(RevisionType::Detached)
        ->and($color)->toHaveCount(2)
        ->and($color->pluck('name')->unique()->all())->toBe(['color'])
        ->and(Attributes::for(Product::create())->history())->toHaveCount(0);
});

it('stages writes with meta and saves or syncs them', function (): void {
    $product = Product::create();
    Attributes::for($product)->set('old', 'x');

    $stage = Attributes::for($product)->stage();

    expect($stage)->toBeInstanceOf(AttributeWriter::class);

    $returned = $stage->set('color', 'red')->meta('color', ['hex' => '#f00'])->meta('ghost', ['a' => 1])->save();

    expect($returned)->toBe($product)
        ->and(Attributes::for($product)->toKeyValue())->toBe(['old' => 'x', 'color' => 'red'])
        ->and($product->getAttachedAttributeMeta('color')?->get('hex'))->toBe('#f00')
        ->and(Attributes::for($product)->has('ghost'))->toBeFalse();

    Attributes::for($product)->stage()->set('size', 'L')->sync();

    expect(Attributes::for($product)->toKeyValue())->toBe(['size' => 'L']);
});

it('prunes trashed attributes older than the given or configured age', function (): void {
    $product = Product::create();
    Attributes::for($product)->setMany(['old' => 1, 'recent' => 2, 'default' => 3]);
    Attributes::for($product)->forget(['old', 'recent', 'default']);

    Attribute::withTrashed()->where('name', 'old')->update(['deleted_at' => Carbon::now()->subDays(60)]);
    Attribute::withTrashed()->where('name', 'recent')->update(['deleted_at' => Carbon::now()->subDays(5)]);
    Attribute::withTrashed()->where('name', 'default')->update(['deleted_at' => Carbon::now()->subDays(40)]);

    expect(Attributes::prune(50))->toBe(1);

    $this->assertDatabaseMissing('attributes', ['name' => 'old']);

    config()->set('attributes.prune_after_days', 'not-a-number');

    expect(Attributes::prune())->toBe(1);

    $this->assertDatabaseMissing('attributes', ['name' => 'default']);
    $this->assertSoftDeleted('attributes', ['name' => 'recent']);
});

it('chains definition calls through the facade', function (): void {
    $manager = Attributes::define(new AttributeDefinitionData('a', AttributeType::String_))
        ->defineMany(new AttributeDefinitionData('b', AttributeType::Integer, required: true, default: 7));

    expect($manager)->toBeInstanceOf(AttributesManager::class)
        ->and(Attributes::has('a'))->toBeTrue()
        ->and(Attributes::requiredNames())->toBe(['b'])
        ->and(Attributes::default('b'))->toBe(7)
        ->and(Attributes::resolveFor(null, 'b')?->type)->toBe(AttributeType::Integer)
        ->and(Attributes::definitionsFor(Product::create()))->toHaveKeys(['a', 'b'])
        ->and(Attributes::isStrict())->toBeFalse();

    Attributes::validate('b', 3);
    Attributes::validateFor(null, 'b', 3);
    Attributes::assertKnown('anything');
    Attributes::assertUnique(Product::create(), 'a', 'x');

    expect(Attributes::forget('a')->has('a'))->toBeFalse()
        ->and(Attributes::flush()->all())->toBe([]);
});
