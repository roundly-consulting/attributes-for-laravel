<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\DefinitionFactory;
use RoundlyConsulting\Attributes\Tests\Fixtures\TeamScopeResolver;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\Attributes\Tests\Models\Team;
use RoundlyConsulting\Attributes\Tests\Models\TeamProduct;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Consumer gap 2026-11-11: `unique => owner` is unique per owner TYPE — every tenant's
 * products share one pool — so one tenant learned that another tenant holds a value.
 * `unique => scope` makes a value unique within a scope model the owner resolves (a team,
 * a tenant), backed by the same `unique_hash` index.
 */
function scopedSku(string|Closure|null $scope = 'team_id', bool $encrypted = false): void
{
    Attributes::define(new AttributeDefinitionData(
        'sku',
        AttributeType::String_,
        unique: UniqueScope::Scope,
        encrypted: $encrypted,
        uniqueScope: $scope,
    ));
}

function teamProduct(?Team $team): TeamProduct
{
    return TeamProduct::query()->create(['team_id' => $team?->getKey()]);
}

it('allows the same value in two different scope models', function (string|Closure $scope, bool $encrypted): void {
    scopedSku($scope, $encrypted);

    teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC');
    $other = teamProduct(Team::query()->create());
    $other->attachAttribute('sku', 'ABC');

    expect($other->getAttachedAttributeValue('sku'))->toBe('ABC')
        ->and(Attribute::query()->where('name', 'sku')->count())->toBe(2);
})->with([
    'attribute' => 'team_id',
    'relation' => 'team',
    'invokable' => TeamScopeResolver::class,
    'closure' => fn (): Closure => fn (Model $owner): mixed => $owner->getAttribute('team_id'),
])->with(['plain' => false, 'encrypted' => true]);

it('refuses the same value for two owners sharing a scope model', function (string|Closure $scope, bool $encrypted): void {
    scopedSku($scope, $encrypted);
    $team = Team::query()->create();

    teamProduct($team)->attachAttribute('sku', 'ABC');
    $other = teamProduct($team);

    expect(fn () => $other->attachAttribute('sku', 'ABC'))
        ->toThrow(DuplicateAttributeValueException::class, 'Attribute [sku] must be unique (scope); the given value already exists.')
        ->and($other->hasAttachedAttribute('sku'))->toBeFalse();
})->with([
    'attribute' => 'team_id',
    'relation' => 'team',
    'invokable' => TeamScopeResolver::class,
    'closure' => fn (): Closure => fn (Model $owner): mixed => $owner->getAttribute('team_id'),
])->with(['plain' => false, 'encrypted' => true]);

it('lets an owner re-save its own scoped value', function (): void {
    scopedSku();
    $product = teamProduct(Team::query()->create());

    $product->attachAttribute('sku', 'ABC');
    $product->attachAttribute('sku', 'ABC');

    expect($product->getAttachedAttributeValue('sku'))->toBe('ABC');
});

it('makes a value unique per owner when the scope is the owner itself', function (): void {
    scopedSku(fn (Model $owner): Model => $owner);
    $team = Team::query()->create();

    $first = teamProduct($team);
    $first->attachAttribute('sku', 'ABC');
    $first->attachAttribute('sku', 'ABC');
    $second = teamProduct($team);
    $second->attachAttribute('sku', 'ABC');

    expect($first->getAttachedAttributeValue('sku'))->toBe('ABC')
        ->and($second->getAttachedAttributeValue('sku'))->toBe('ABC');
});

it('keeps the legacy owner scope unique per owner type across scope models', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Owner));

    teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC');

    expect(fn () => teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC'))
        ->toThrow(DuplicateAttributeValueException::class, 'Attribute [sku] must be unique (owner); the given value already exists.');
});

it('keeps the owner-scope hash of existing rows unchanged', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Owner));

    $product = Product::query()->create();
    $product->attachAttribute('sku', 'ABC');

    $payload = '5:owner'.strlen($product->getMorphClass()).':'.$product->getMorphClass().'3:sku3:ABC';

    expect(Attribute::query()->value('unique_hash'))->toBe(hash('sha256', $payload));
});

it('reads the scope from attributes.unique_scope when the definition names none', function (): void {
    config()->set('attributes.unique_scope', 'team_id');
    scopedSku(null);
    $team = Team::query()->create();

    teamProduct($team)->attachAttribute('sku', 'ABC');
    teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC');

    expect(fn () => teamProduct($team)->attachAttribute('sku', 'ABC'))
        ->toThrow(DuplicateAttributeValueException::class);
});

it('prefers the definition scope over attributes.unique_scope', function (): void {
    config()->set('attributes.unique_scope', 'missing_column');
    scopedSku('team_id');

    teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC');
    teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC');

    expect(Attribute::query()->where('name', 'sku')->count())->toBe(2);
});

it('parses a config definition with a unique scope', function (): void {
    $definition = DefinitionFactory::fromArray('sku', ['type' => 'string', 'unique' => 'scope', 'unique_scope' => 'team_id']);

    expect($definition->unique)->toBe(UniqueScope::Scope)
        ->and($definition->uniqueScope)->toBe('team_id');
});

it('refuses a unique_scope on a definition that is not scoped', function (mixed $unique): void {
    expect(fn () => DefinitionFactory::fromArray('sku', ['unique' => $unique, 'unique_scope' => 'team_id']))
        ->toThrow(InvalidConfigurationException::class, 'attributes.definitions.sku.unique_scope');
})->with(['owner', 'global', 'none', null, true]);

it('refuses a unique_scope that is neither a name nor a closure', function (mixed $scope): void {
    expect(fn () => DefinitionFactory::fromArray('sku', ['unique' => 'scope', 'unique_scope' => $scope]))
        ->toThrow(InvalidConfigurationException::class, 'attributes.definitions.sku.unique_scope');
})->with([42, '  ', '']);

it('refuses a scoped definition that names no scope anywhere', function (): void {
    scopedSku(null);

    expect(fn () => teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC'))
        ->toThrow(InvalidConfigurationException::class, 'attributes.unique_scope');
});

it('refuses an attributes.unique_scope that is not a string', function (): void {
    config()->set('attributes.unique_scope', 42);
    scopedSku(null);

    expect(fn () => teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC'))
        ->toThrow(InvalidConfigurationException::class, 'attributes.unique_scope');
});

it('refuses a class-string scope that is not a ResolvesUniqueScope', function (): void {
    scopedSku(Team::class);

    expect(fn () => teamProduct(Team::query()->create())->attachAttribute('sku', 'ABC'))
        ->toThrow(InvalidConfigurationException::class, 'ResolvesUniqueScope');
});

it('fails closed when the owner resolves no scope', function (mixed $resolved): void {
    scopedSku(fn (): mixed => $resolved);
    $product = teamProduct(null);

    expect(fn () => $product->attachAttribute('sku', 'ABC'))
        ->toThrow(AttributesException::class, 'Attribute [sku] is unique per scope, but its owner resolved no scope')
        ->and($product->hasAttachedAttribute('sku'))->toBeFalse();
})->with(['null' => null, 'blank' => '', 'array' => fn (): array => [1], 'unsaved model' => fn (): Team => new Team]);

it('resolves a relation scope under preventLazyLoading', function (): void {
    scopedSku('team');
    $team = Team::query()->create();
    teamProduct($team)->attachAttribute('sku', 'ABC');

    Model::preventLazyLoading();

    try {
        $other = TeamProduct::query()->create(['team_id' => $team->getKey()]);

        expect(fn () => $other->attachAttribute('sku', 'ABC'))->toThrow(DuplicateAttributeValueException::class);
    } finally {
        Model::preventLazyLoading(false);
    }
});

it('checks scoped uniqueness through the facade', function (): void {
    scopedSku();
    $team = Team::query()->create();
    teamProduct($team)->attachAttribute('sku', 'ABC');

    Attributes::assertUnique(teamProduct(Team::query()->create()), 'sku', 'ABC');

    expect(fn () => Attributes::assertUnique(teamProduct($team), 'sku', 'ABC'))
        ->toThrow(DuplicateAttributeValueException::class);
});

it('finds a same-scope plain value stored before the definition became unique', function (): void {
    $team = Team::query()->create();
    teamProduct($team)->attachAttribute('sku', 'ABC');
    teamProduct(Team::query()->create())->attachAttribute('sku', 'XYZ');

    scopedSku();

    expect(Attribute::query()->whereNotNull('unique_hash')->count())->toBe(0)
        ->and(fn () => teamProduct($team)->attachAttribute('sku', 'ABC'))->toThrow(DuplicateAttributeValueException::class);

    // The same legacy value in another scope model does not count.
    $elsewhere = teamProduct(Team::query()->create());
    $elsewhere->attachAttribute('sku', 'XYZ');

    expect($elsewhere->getAttachedAttributeValue('sku'))->toBe('XYZ');
});

it('enforces scoped values at the database level', function (): void {
    scopedSku();
    $team = Team::query()->create();

    teamProduct($team)->attachAttribute('sku', 'ABC');
    $row = (array) DB::table('attributes')->first();

    unset($row['id']);
    $row['owner_id'] = teamProduct($team)->getKey();

    expect(fn () => DB::table('attributes')->insert($row))->toThrow(UniqueConstraintViolationException::class);
});

it('turns a lost scoped-value race into DuplicateAttributeValueException', function (): void {
    scopedSku();
    $team = Team::query()->create();

    $competitor = teamProduct($team);
    $competitor->attachAttribute('sku', 'ABC');
    $row = (array) DB::table('attributes')->first();
    DB::table('attributes')->delete();

    $raced = false;
    DB::listen(function (QueryExecuted $query) use (&$raced, $row): void {
        if ($raced || ! str_contains($query->sql, 'attributes')) {
            return;
        }

        $raced = true;
        DB::table('attributes')->insert($row);
    });

    $product = teamProduct($team);

    expect(fn () => $product->attachAttribute('sku', 'ABC'))->toThrow(DuplicateAttributeValueException::class)
        ->and($raced)->toBeTrue();
});

it('hashes a row created without the owner relation by loading its owner', function (): void {
    scopedSku('team');
    $team = Team::query()->create();
    teamProduct($team)->attachAttribute('sku', 'ABC');
    $other = teamProduct($team);

    expect(fn () => Attribute::query()->create([
        'name' => 'sku',
        'value' => 'ABC',
        'owner_type' => $other->getMorphClass(),
        'owner_id' => $other->getKey(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('hashes a value set before its owner keys once they are known', function (): void {
    scopedSku();
    $team = Team::query()->create();
    teamProduct($team)->attachAttribute('sku', 'ABC');
    $other = teamProduct(Team::query()->create());

    $attribute = new Attribute;
    $attribute->forceFill(['name' => 'sku', 'value' => 'ABC']);
    $attribute->forceFill(['owner_type' => $other->getMorphClass(), 'owner_id' => $other->getKey()]);
    $attribute->save();

    expect($attribute->fresh()?->unique_hash)->not->toBeNull()
        ->and($attribute->fresh()?->unique_hash)->not->toBe(Attribute::query()->whereKeyNot($attribute->getKey())->value('unique_hash'));
});

it('refuses a scoped value whose owner row is gone', function (): void {
    scopedSku();

    expect(fn () => Attribute::query()->create([
        'name' => 'sku',
        'owner_type' => (new TeamProduct)->getMorphClass(),
        'owner_id' => 999_999,
        'value' => 'ABC',
    ]))->toThrow(AttributesException::class, 'resolved no scope');
});

it('recomputes a scoped hash on restore and refuses a value taken in the meantime', function (): void {
    scopedSku('team');
    $team = Team::query()->create();

    $first = teamProduct($team);
    $first->attachAttribute('sku', 'S1');
    $row = Attribute::query()->firstOrFail();
    $hash = $row->unique_hash;
    $row->delete();

    $row = Attribute::onlyTrashed()->firstOrFail();
    $row->restore();

    expect($row->fresh()?->unique_hash)->toBe($hash);

    $row->delete();
    teamProduct($team)->attachAttribute('sku', 'S1');

    expect(fn () => Attribute::onlyTrashed()->firstOrFail()->restore())
        ->toThrow(DuplicateAttributeValueException::class);
});

it('never puts another owner, its scope or the value in the duplicate exception', function (UniqueScope $unique): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: $unique, uniqueScope: $unique === UniqueScope::Scope ? 'team_id' : null));
    $team = Team::query()->create(['id' => 4242]);

    $holder = TeamProduct::query()->create(['id' => 7777, 'team_id' => $team->getKey()]);
    $holder->attachAttribute('sku', 'SECRET-SKU-1');
    $caller = teamProduct($team);

    try {
        $caller->attachAttribute('sku', 'SECRET-SKU-1');
        $this->fail('Expected a duplicate.');
    } catch (DuplicateAttributeValueException $exception) {
        expect($exception->getMessage())->toBe("Attribute [sku] must be unique ({$unique->value}); the given value already exists.")
            ->and($exception->getMessage())->not->toContain('7777')->not->toContain('4242')
            ->not->toContain('SECRET')->not->toContain(TeamProduct::class)
            ->and(get_object_vars($exception))->toBe(['attributeName' => 'sku', 'scope' => $unique]);
    }
})->with([UniqueScope::Owner, UniqueScope::Global_, UniqueScope::Scope]);
