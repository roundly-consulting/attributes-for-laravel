<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Attributes\AttributesManager;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Support\RevisionActor;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\Attributes\Tests\Models\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

beforeEach(function (): void {
    config()->set('attributes.history.enabled', true);
    config()->set('attributes.history.actor.enabled', true);
});

afterEach(function (): void {
    Attributes::resolveActorUsing(null);
});

it('migrates a nullable actor morph onto the revisions table', function (): void {
    expect(Schema::hasColumns('attribute_revisions', ['actor_type', 'actor_id']))->toBeTrue();
});

it('records no actor unless actor recording is on', function (): void {
    config()->set('attributes.history.actor.enabled', false);
    Auth::setUser(User::query()->create());

    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    $revision = AttributeRevision::query()->sole();

    expect($revision->actor_type)->toBeNull()
        ->and($revision->actor_id)->toBeNull();
});

it('records the authenticated user of the default guard by default', function (): void {
    $user = User::query()->create();
    Auth::setUser($user);

    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    $revision = AttributeRevision::query()->sole();

    expect($revision->actor_type)->toBe($user->getMorphClass())
        ->and((int) $revision->actor_id)->toBe($user->getKey())
        ->and($revision->actor?->is($user))->toBeTrue()
        ->and($revision->actorType())->toBe($user->getMorphClass());
});

it('records nobody when no user is authenticated', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    $revision = AttributeRevision::query()->sole();

    expect($revision->actorType())->toBeNull()
        ->and($revision->actorId())->toBeNull()
        ->and($revision->actor)->toBeNull();
});

it('reads the actor from the configured guard', function (): void {
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
    config()->set('attributes.history.actor.guard', 'admin');

    $web = User::query()->create(['name' => 'web']);
    $admin = User::query()->create(['name' => 'admin']);
    Auth::guard('web')->setUser($web);
    Auth::guard('admin')->setUser($admin);

    Product::query()->create()->attachAttribute('color', 'red');

    expect((int) AttributeRevision::query()->sole()->actor_id)->toBe($admin->getKey());
});

it('treats a blank guard as the default guard', function (): void {
    config()->set('attributes.history.actor.guard', ' ');
    $user = User::query()->create();
    Auth::setUser($user);

    Product::query()->create()->attachAttribute('color', 'red');

    expect((int) AttributeRevision::query()->sole()->actor_id)->toBe($user->getKey());
});

it('refuses a guard that is not a string', function (): void {
    config()->set('attributes.history.actor.guard', 42);

    expect(fn () => Product::query()->create()->attachAttribute('color', 'red'))
        ->toThrow(InvalidConfigurationException::class, 'attributes.history.actor.guard');
});

it('records the actor a write names with by(), over the authenticated user', function (): void {
    Auth::setUser(User::query()->create());
    $editor = User::query()->create(['name' => 'editor']);
    $product = Product::query()->create();

    Attributes::for($product)->by($editor)->set('color', 'red');

    expect((int) AttributeRevision::query()->sole()->actor_id)->toBe($editor->getKey());
});

it('records nobody for a write named by(null)', function (): void {
    Auth::setUser(User::query()->create());
    $product = Product::query()->create();

    Attributes::for($product)->by(null)->set('color', 'red');

    expect(AttributeRevision::query()->sole()->actorType())->toBeNull();
});

it('records the named actor on every write path of the handle', function (): void {
    $editor = User::query()->create();
    $product = Product::query()->create();
    $handle = Attributes::for($product)->by($editor);

    $handle->set('color', 'red');
    $handle->setMany(['size' => 'L', 'weight' => 2]);
    $handle->setValidated(['height' => 3]);
    $handle->meta('color', ['source' => 'import']);
    $handle->sync(['color' => 'blue', 'size' => 'M']);
    $handle->forget('size');
    $handle->set('extra', 1);
    $handle->forgetExcept(['color']);
    $handle->stage()->set('color', 'green')->save();

    $revisions = AttributeRevision::query()->get();

    expect($revisions->count())->toBeGreaterThan(8)
        ->and($revisions->every(fn (AttributeRevision $revision): bool => (int) $revision->actor_id === $editor->getKey()
            && $revision->actor_type === $editor->getMorphClass()))->toBeTrue();
});

it('names the actor for the handle\'s writes only', function (): void {
    $editor = User::query()->create();
    $product = Product::query()->create();

    Attributes::for($product)->by($editor)->set('color', 'red');
    Attributes::for($product)->set('size', 'L');

    expect(AttributeRevision::query()->where('name', 'color')->sole()->actorId())->not->toBeNull()
        ->and(AttributeRevision::query()->where('name', 'size')->sole()->actorId())->toBeNull();
});

it('refuses to name an unsaved actor', function (): void {
    $product = Product::query()->create();

    expect(fn () => Attributes::for($product)->by(new User))
        ->toThrow(AttributesException::class, 'A revision actor must be a saved Eloquent model or null, ['.User::class.'] given.');
});

it('asks the host resolver for the actor when a write names none', function (): void {
    Auth::setUser(User::query()->create());
    $system = User::query()->create(['name' => 'system']);

    Attributes::resolveActorUsing(fn (): User => $system);

    Product::query()->create()->attachAttribute('color', 'red');

    expect((int) AttributeRevision::query()->sole()->actor_id)->toBe($system->getKey());
});

it('prefers by() over the host resolver', function (): void {
    $system = User::query()->create();
    $editor = User::query()->create();
    Attributes::resolveActorUsing(fn (): User => $system);

    Attributes::for(Product::query()->create())->by($editor)->set('color', 'red');

    expect((int) AttributeRevision::query()->sole()->actor_id)->toBe($editor->getKey());
});

it('lets the resolver return null for nobody, and null restores the auth guard', function (): void {
    $user = User::query()->create();
    Auth::setUser($user);
    $product = Product::query()->create();

    Attributes::resolveActorUsing(fn (): mixed => null);
    $product->attachAttribute('color', 'red');

    Attributes::resolveActorUsing(null);
    $product->attachAttribute('size', 'L');

    expect(AttributeRevision::query()->where('name', 'color')->sole()->actorId())->toBeNull()
        ->and((int) AttributeRevision::query()->where('name', 'size')->sole()->actor_id)->toBe($user->getKey());
});

it('rejects a resolver that returns something that is not a saved model', function (mixed $returned, string $type): void {
    Attributes::resolveActorUsing(fn (): mixed => $returned);

    expect(fn () => Product::query()->create()->attachAttribute('color', 'red'))
        ->toThrow(AttributesException::class, "[{$type}] given");

    expect(AttributeRevision::query()->count())->toBe(0);
})->with([
    'a string' => ['someone', 'string'],
    'an unsaved model' => [new User, User::class],
]);

it('does not resolve an actor while history is off', function (): void {
    config()->set('attributes.history.enabled', false);
    $called = false;
    Attributes::resolveActorUsing(function () use (&$called): mixed {
        $called = true;

        return null;
    });

    Product::query()->create()->attachAttribute('color', 'red');

    expect($called)->toBeFalse();
});

it('records the actor on detach revisions and the owner-delete cascade', function (): void {
    $user = User::query()->create();
    Auth::setUser($user);
    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    $product->delete();

    expect(AttributeRevision::query()->count())->toBe(2)
        ->and(AttributeRevision::query()->get()->every(fn (AttributeRevision $revision): bool => (int) $revision->actor_id === $user->getKey()))->toBeTrue();
});

it('keeps recording the actor through the fake, which still records the writes', function (): void {
    $fake = Attributes::fake();
    $editor = User::query()->create();
    $product = Product::query()->create();

    Attributes::for($product)->by($editor)->set('color', 'red');
    Attributes::for($product)->by($editor)->setValidated(['size' => 'L']);

    $fake->assertSet($product, 'color', 'red');
    $fake->assertSet($product, 'size', 'L');

    expect(AttributeRevision::query()->pluck('actor_id')->map(fn (mixed $id): int => (int) $id)->unique()->all())
        ->toBe([$editor->getKey()]);
});

it('injects the manager whose resolver is the facade\'s', function (): void {
    $system = User::query()->create();

    app(AttributesManager::class)->resolveActorUsing(fn (): User => $system);

    Product::query()->create()->attachAttribute('color', 'red');

    expect((int) AttributeRevision::query()->sole()->actor_id)->toBe($system->getKey());
});

it('restores the outer named actor after a nested write', function (): void {
    $outer = User::query()->create();
    $inner = User::query()->create();
    $actors = app(RevisionActor::class);

    $seen = $actors->using($outer, function () use ($actors, $inner): array {
        $nested = $actors->using($inner, fn (): ?Illuminate\Database\Eloquent\Model => $actors->current());

        return [$nested, $actors->current()];
    });

    expect($seen[0]?->is($inner))->toBeTrue()
        ->and($seen[1]?->is($outer))->toBeTrue()
        ->and($actors->current())->toBeNull();
});

it('records nobody where no auth is bound', function (): void {
    expect(new RevisionActor(new Container)->current())->toBeNull();
});
