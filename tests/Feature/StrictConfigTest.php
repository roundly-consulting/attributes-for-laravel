<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Attributes\Actions\PruneAttributesAction;
use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Registry\DefinitionFactory;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * A typo in the host's attributes config fails loudly. Before: a non-number
 * `prune_after_days` became 30, a blank table name became `attributes`, a typo'd
 * definition `type` became `string`, and a typo'd `unique` silently switched uniqueness off.
 */
function bootAttributesProvider(): void
{
    app()->forgetInstance(AttributeRegistry::class);
    $provider = new AttributesServiceProvider(app());
    $provider->register();
    $provider->boot();
}

it('refuses a junk or negative prune age (strict config)', function (mixed $days): void {
    config()->set('attributes.prune_after_days', $days);

    expect(fn () => app(PruneAttributesAction::class)->execute())
        ->toThrow(InvalidConfigurationException::class, 'attributes.prune_after_days');
})->with(['word' => 'thirty', 'decimal' => '7.5', 'blank' => '', 'negative' => '-1', 'bool' => true]);

it('reads a canonical prune age string and defaults an absent one (strict config)', function (): void {
    config()->set('attributes.prune_after_days', ' 7 ');
    expect(app(PruneAttributesAction::class)->execute())->toBe(0);

    config()->set('attributes.prune_after_days', null);
    expect(app(PruneAttributesAction::class)->execute())->toBe(0);
});

it('fails the prune command on a junk configured age (strict config)', function (): void {
    config()->set('attributes.prune_after_days', 'thirty');

    expect(fn () => Artisan::call('attributes:prune', ['--force' => true]))
        ->toThrow(InvalidConfigurationException::class, 'attributes.prune_after_days');
});

it('refuses a junk --days option instead of purging every trashed attribute (strict config)', function (string $days): void {
    $this->artisan('attributes:prune', ['--days' => $days, '--force' => true])
        ->expectsOutputToContain('--days must be a whole number of days')
        ->assertFailed();
})->with(['thirty', '7.5', '-1', '']);

it('refuses a blank or non-string table name (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, $key);
})->with([
    'blank table' => ['attributes.table', '', fn () => (new Attribute)->getTable()],
    'int table' => ['attributes.table', 5, fn () => (new Attribute)->getTable()],
    'blank history table' => ['attributes.history.table', ' ', fn () => (new AttributeRevision)->getTable()],
    'array history table' => ['attributes.history.table', ['revisions'], fn () => (new AttributeRevision)->getTable()],
]);

it('defaults absent table names (strict config)', function (): void {
    config()->set('attributes.table', null);
    config()->set('attributes.history.table', null);

    expect((new Attribute)->getTable())->toBe('attributes')
        ->and((new AttributeRevision)->getTable())->toBe('attribute_revisions');
});

it('refuses a typo in a definition type instead of storing it as a string (strict config)', function (): void {
    config()->set('attributes.definitions', ['rating' => ['type' => 'integr']]);

    expect(fn () => bootAttributesProvider())
        ->toThrow(InvalidConfigurationException::class, 'attributes.definitions.rating.type');
});

it('refuses a typo in a definition unique scope instead of switching uniqueness off (strict config)', function (mixed $unique): void {
    expect(fn () => DefinitionFactory::fromArray('sku', ['unique' => $unique]))
        ->toThrow(InvalidConfigurationException::class, 'attributes.definitions.sku.unique');
})->with(['globl', 'Global', '', 42]);

it('refuses non-array rules instead of dropping them (strict config)', function (): void {
    expect(fn () => DefinitionFactory::fromArray('rating', ['rules' => 'min:1|max:5']))
        ->toThrow(InvalidConfigurationException::class, 'attributes.definitions.rating.rules');
});

it('reads required and encrypted as booleans, refusing junk (strict config)', function (string $flag): void {
    expect(DefinitionFactory::fromArray('token', [$flag => 'false'])->{$flag})->toBeFalse()
        ->and(DefinitionFactory::fromArray('token', [$flag => 'on'])->{$flag})->toBeTrue()
        ->and(fn () => DefinitionFactory::fromArray('token', [$flag => 'sometimes']))
        ->toThrow(InvalidConfigurationException::class, "attributes.definitions.token.{$flag}");
})->with(['required', 'encrypted']);

it('still accepts every valid definition spelling (strict config)', function (): void {
    $definition = DefinitionFactory::fromArray('sku', [
        'type' => AttributeType::Integer,
        'unique' => UniqueScope::Global_,
    ]);

    expect($definition->type)->toBe(AttributeType::Integer)
        ->and($definition->unique)->toBe(UniqueScope::Global_)
        ->and(DefinitionFactory::fromArray('sku', ['unique' => false])->unique)->toBe(UniqueScope::None)
        ->and(DefinitionFactory::fromArray('sku', ['unique' => null])->unique)->toBe(UniqueScope::None);
});

it('refuses a definitions entry that is not an array (strict config)', function (): void {
    config()->set('attributes.definitions', ['nickname' => 'string']);

    expect(fn () => bootAttributesProvider())
        ->toThrow(InvalidConfigurationException::class, 'attributes.definitions.nickname');
});

it('refuses a definitions value that is not an array (strict config)', function (): void {
    config()->set('attributes.definitions', 'rating');

    expect(fn () => bootAttributesProvider())
        ->toThrow(InvalidConfigurationException::class, 'attributes.definitions');
});
