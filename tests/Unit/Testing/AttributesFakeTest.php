<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Attributes\AttributesManager;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Testing\AttributesFake;
use RoundlyConsulting\Attributes\Testing\RecordedWrite;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/*
 * Every assert gets a passing and a failing case, driven through a HasAttributes trait call
 * (the path that used to reach the actions directly) and, where the trait has no verb,
 * through the facade.
 *
 * Each row: [setup before faking, the action, the positive assert, the assertNothing*].
 */
dataset('recorded writes', [
    'set (trait)' => [
        null,
        fn (Product $p) => $p->attachAttribute('color', 'red'),
        fn (AttributesFake $fake, Product $p) => $fake->assertSet($p, 'color', 'red'),
        'assertNothingSet',
    ],
    'setMany (trait)' => [
        null,
        fn (Product $p) => $p->attachAttributes(['color' => 'red', 'size' => 'L']),
        fn (AttributesFake $fake, Product $p) => $fake->assertSet($p, 'size', 'L'),
        'assertNothingSet',
    ],
    'stage()->save() (trait)' => [
        null,
        fn (Product $p) => $p->attributes()->set('color', 'red')->save(),
        fn (AttributesFake $fake, Product $p) => $fake->assertSet($p, 'color'),
        'assertNothingSet',
    ],
    'forget (trait)' => [
        fn (Product $p) => $p->attachAttribute('color', 'red'),
        fn (Product $p) => $p->detachAttribute('color'),
        fn (AttributesFake $fake, Product $p) => $fake->assertForgotten($p, 'color'),
        'assertNothingForgotten',
    ],
    'forget many (trait)' => [
        fn (Product $p) => $p->attachAttributes(['color' => 'red', 'size' => 'L']),
        fn (Product $p) => $p->destroyAttributes(['color', 'size']),
        fn (AttributesFake $fake, Product $p) => $fake->assertForgotten($p, 'size'),
        'assertNothingForgotten',
    ],
    'forgetExcept (trait)' => [
        fn (Product $p) => $p->attachAttributes(['color' => 'red', 'size' => 'L']),
        fn (Product $p) => $p->destroyAttributesExcept(['color']),
        fn (AttributesFake $fake, Product $p) => $fake->assertForgotten($p, 'size'),
        'assertNothingForgotten',
    ],
    'sync (trait)' => [
        null,
        fn (Product $p) => $p->syncAttributes(['color' => 'red', 'size' => 'L']),
        fn (AttributesFake $fake, Product $p) => $fake->assertSynced($p, ['size' => 'L', 'color' => 'red']),
        'assertNothingSynced',
    ],
    'stage()->sync() (trait)' => [
        null,
        fn (Product $p) => $p->attributes()->set('color', 'red')->sync(),
        fn (AttributesFake $fake, Product $p) => $fake->assertSynced($p),
        'assertNothingSynced',
    ],
    'meta (trait)' => [
        fn (Product $p) => $p->attachAttribute('color', 'red'),
        fn (Product $p) => $p->syncAttributeMeta('color', collect(['hex' => '#f00'])),
        fn (AttributesFake $fake, Product $p) => $fake->assertMetaSet($p, 'color', ['hex' => '#f00']),
        'assertNothingMetaSet',
    ],
    'prune (facade)' => [
        null,
        fn () => Attributes::prune(),
        fn (AttributesFake $fake) => $fake->assertPruned(),
        'assertNothingPruned',
    ],
]);

it('passes the assert and fails the assertNothing once the write happened', function (?Closure $setup, Closure $act, Closure $assert, string $nothing): void {
    $product = Product::create();
    $setup?->__invoke($product);

    $fake = Attributes::fake();
    $act($product);

    $assert($fake, $product);

    expect(fn () => $fake->{$nothing}())->toThrow(AssertionFailedError::class);
})->with('recorded writes');

it('fails the assert and passes the assertNothing when the write did not happen', function (?Closure $setup, Closure $act, Closure $assert, string $nothing): void {
    $product = Product::create();
    $setup?->__invoke($product);

    $fake = Attributes::fake();

    $fake->{$nothing}();

    expect(fn () => $assert($fake, $product))->toThrow(AssertionFailedError::class);
})->with('recorded writes');

it('swaps the facade root and the container binding', function (): void {
    $fake = Attributes::fake();

    expect($fake)->toBeInstanceOf(AttributesManager::class)
        ->and(Attributes::getFacadeRoot())->toBe($fake)
        ->and(app(AttributesManager::class))->toBe($fake);
});

it('records writes made through an injected manager', function (): void {
    $fake = Attributes::fake();
    $product = Product::create();

    app(AttributesManager::class)->for($product)->set('color', 'red');

    $fake->assertSet($product, 'color', 'red');
});

it('records the prune command', function (): void {
    $fake = Attributes::fake();

    $this->artisan('attributes:prune', ['--force' => true])->assertSuccessful();

    $fake->assertNothingPruned();

    $product = Product::create();
    $product->attachAttribute('old', 1);
    $product->detachAttribute('old');
    $product->attachedAttributes()->withTrashed()->update(['deleted_at' => now()->subYear()]);

    $this->artisan('attributes:prune', ['--force' => true])->assertSuccessful();

    $fake->assertPruned();
});

it('passes writes through to the database', function (): void {
    Attributes::fake();
    $product = Product::create();

    $product->attachAttribute('color', 'red');

    expect(Attributes::for($product)->get('color'))->toBe('red');
});

it('keeps definitions on the real registry and does not record them', function (): void {
    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer));

    $fake = Attributes::fake();
    Attributes::define(new AttributeDefinitionData('color', AttributeType::String_));

    expect(Attributes::has('rating'))->toBeTrue()
        ->and(Attributes::has('color'))->toBeTrue();

    $fake->assertNothingWritten();

    expect(fn () => Product::create()->attachAttribute('rating', 'x'))->toThrow(InvalidAttributeValueException::class);

    $fake->assertNothingSet();
});

it('checks the value, the synced set and the meta when given', function (): void {
    $fake = Attributes::fake();
    $product = Product::create();

    $product->attachAttribute('color', 'red');
    $product->attachAttribute('note', null);
    $product->syncAttributes(['color' => 'red']);
    $product->syncAttributeMeta('color', collect(['hex' => '#f00']));

    $fake->assertSet($product, 'note', null);
    $fake->assertMetaSet($product, 'color');

    expect(fn () => $fake->assertSet($product, 'color', 'blue'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertSet($product, 'color', null))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertSet(Product::create(), 'color'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertSynced($product, ['color' => 'blue']))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertMetaSet($product, 'color', ['hex' => '#000']))->toThrow(AssertionFailedError::class);
});

it('assertNothingWritten passes on a clean fake and fails after a write', function (): void {
    $fake = Attributes::fake();
    $product = Product::create();

    $fake->assertNothingWritten();

    $product->attachAttribute('color', 'red');

    expect(fn () => $fake->assertNothingWritten())->toThrow(AssertionFailedError::class, 'recorded [set]');
});

it('exposes the recorded writes', function (): void {
    $fake = Attributes::fake();
    $product = Product::create();

    Attributes::for($product)->setMany(['a' => 1, 'b' => 2]);
    Attributes::for($product)->forget('a', forceDelete: true);

    expect($fake->recorded())->toHaveCount(3)
        ->each->toBeInstanceOf(RecordedWrite::class)
        ->and(array_map(static fn (RecordedWrite $write): string => $write->verb, $fake->recorded()))->toBe(['set', 'set', 'forget'])
        ->and($fake->recorded()[2]->forceDelete)->toBeTrue();
});
