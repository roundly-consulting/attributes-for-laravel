<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Http\Resources\AttributeRevisionResource;
use RoundlyConsulting\Attributes\Http\Resources\RevisionActorResource;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Support\AttributesConfig;
use RoundlyConsulting\Attributes\Tests\Fixtures\AbstractRevisionActorResource;
use RoundlyConsulting\Attributes\Tests\Fixtures\NamedRevisionActorResource;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\Attributes\Tests\Models\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

beforeEach(function (): void {
    config()->set('attributes.history.enabled', true);
    config()->set('attributes.history.actor.enabled', true);
    Relation::morphMap([], merge: false);
});

afterEach(function (): void {
    Relation::morphMap([], merge: false);
});

/**
 * @return array<string, mixed>
 */
function attributesRenderRevision(AttributeRevision $revision): array
{
    return new AttributeRevisionResource($revision)->resolve(Request::create('/'));
}

function attributesRevisionBy(?Model $actor, string $name = 'color', mixed $value = 'red'): AttributeRevision
{
    Attributes::for(Product::query()->create())->by($actor)->set($name, $value, ['source' => 'import']);

    return AttributeRevision::query()->where('name', $name)->latest('id')->firstOrFail();
}

it('renders a revision with its actor as id and class basename, no class name over the wire', function (): void {
    Carbon::setTestNow('2026-11-09 10:00:00');
    $user = User::query()->create();

    $revision = attributesRevisionBy($user);
    $rendered = attributesRenderRevision($revision);

    expect($rendered)->toBe([
        'id' => $revision->getKey(),
        'name' => 'color',
        'type' => RevisionType::Attached->value,
        'old_value' => null,
        'new_value' => 'red',
        'old_value_type' => null,
        'new_value_type' => 'string',
        'old_meta' => null,
        'new_meta' => ['source' => 'import'],
        'actor' => ['id' => $revision->actorId(), 'type' => 'User'],
        'created_at' => '2026-11-09T10:00:00.000000Z',
    ])
        ->and((int) $revision->actorId())->toBe($user->getKey())
        // json_encode escapes a backslash, so a leaked class name would show as `\\`.
        ->and(json_encode($rendered, JSON_THROW_ON_ERROR))->not->toContain('\\\\')
        ->and(json_encode($rendered, JSON_THROW_ON_ERROR))->not->toContain('Tests');

    Carbon::setTestNow();
});

it('renders a morph-mapped actor by its model basename, not the alias', function (): void {
    Relation::morphMap(['member' => User::class]);
    $user = User::query()->create();

    $rendered = attributesRenderRevision(attributesRevisionBy($user));

    expect($rendered['actor'])->toBe(['id' => $rendered['actor']['id'], 'type' => 'User'])
        ->and(json_encode($rendered, JSON_THROW_ON_ERROR))->not->toContain('member');
});

it('renders a null actor for a revision nobody was recorded for', function (): void {
    expect(attributesRenderRevision(attributesRevisionBy(null))['actor'])->toBeNull();
});

it('renders an actor whose row is gone', function (): void {
    $user = User::query()->create();
    $revision = attributesRevisionBy($user);
    $user->delete();

    expect(attributesRenderRevision($revision->refresh())['actor']['type'])->toBe('User');
});

it('renders the old value of an update', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('rating', 4);
    $product->attachAttribute('rating', 5);

    $rendered = attributesRenderRevision($product->history('rating')->first());

    expect($rendered['type'])->toBe('updated')
        ->and($rendered['old_value'])->toBe('4')
        ->and($rendered['new_value'])->toBe('5');
});

it('reads no actor from a revision whose actor columns were never migrated', function (): void {
    Model::preventAccessingMissingAttributes();

    $revision = new AttributeRevision;
    $revision->setRawAttributes(['id' => 1, 'name' => 'color', 'type' => 'attached']);

    expect($revision->actorType())->toBeNull()
        ->and($revision->actorId())->toBeNull()
        ->and(new RevisionActorResource($revision)->resolve(Request::create('/')))->toBe(['id' => null, 'type' => null]);

    Model::preventAccessingMissingAttributes(false);
});

it('renders the actor through the resource the host configured', function (): void {
    config()->set('attributes.resources.revision_actor', NamedRevisionActorResource::class);
    $user = User::query()->create(['name' => 'Jane']);

    $rendered = attributesRenderRevision(attributesRevisionBy($user));

    expect($rendered['actor'])->toBe(['id' => $rendered['actor']['id'], 'name' => 'Jane']);
});

it('falls back to the package resource for a blank or null setting', function (mixed $value): void {
    config()->set('attributes.resources.revision_actor', $value);

    expect(AttributesConfig::revisionActorResource())->toBe(RevisionActorResource::class);
})->with(['null' => [null], 'blank' => ['  ']]);

it('refuses a revision actor resource that is not an instantiable JsonResource subclass', function (mixed $value): void {
    config()->set('attributes.resources.revision_actor', $value);

    expect(fn () => AttributesConfig::revisionActorResource())
        ->toThrow(InvalidConfigurationException::class, 'attributes.resources.revision_actor');
})->with([
    'a non-resource class' => [stdClass::class],
    'the bare base' => [JsonResource::class],
    'an abstract resource' => [AbstractRevisionActorResource::class],
    'not a string' => [42],
]);
