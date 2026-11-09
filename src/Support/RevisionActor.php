<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;

/**
 * Who a revision records as its actor, in order: the actor a write named
 * (`Attributes::for($model)->by($user)`, null = explicitly nobody), else what the host's
 * resolver returns (`Attributes::resolveActorUsing()`), else the authenticated user of
 * `attributes.history.actor.guard`. A container singleton.
 *
 * @internal reached through the owner handle and the manager
 */
final class RevisionActor
{
    /**
     * The actors named by the writes in progress, innermost last.
     *
     * @var list<Model|null>
     */
    private array $named = [];

    /**
     * @var (Closure(): mixed)|null
     */
    private ?Closure $resolver = null;

    public function __construct(private readonly Container $container) {}

    /**
     * Run `$callback` with `$actor` as the actor of every revision it records.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function using(?Model $actor, Closure $callback): mixed
    {
        $this->named[] = $actor;

        try {
            return $callback();
        } finally {
            array_pop($this->named);
        }
    }

    /**
     * @param  (Closure(): mixed)|null  $resolver  returns the actor (a saved Model) or null; null restores the auth guard
     */
    public function resolveUsing(?Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * @throws AttributesException when a resolver returns something that is not a saved Model or null
     */
    public function current(): ?Model
    {
        if ($this->named !== []) {
            return $this->named[array_key_last($this->named)];
        }

        if ($this->resolver !== null) {
            $actor = ($this->resolver)();

            if ($actor !== null && ! ($actor instanceof Model && self::saved($actor))) {
                throw AttributesException::invalidActor($actor);
            }

            return $actor;
        }

        return $this->authenticated();
    }

    /**
     * Whether the model can be pointed at: it has a row and a key.
     */
    public static function saved(Model $actor): bool
    {
        return $actor->exists && $actor->getKey() !== null;
    }

    private function authenticated(): ?Model
    {
        if (! $this->container->bound(AuthFactory::class)) {
            return null;
        }

        $user = $this->container->make(AuthFactory::class)->guard(AttributesConfig::actorGuard())->user();

        return $user instanceof Model && self::saved($user) ? $user : null;
    }
}
