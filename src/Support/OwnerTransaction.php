<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;

/**
 * Runs an owner's attribute writes in one transaction on the connection its
 * attribute rows live on (a savepoint when a transaction is already open). The
 * package's events implement ShouldDispatchAfterCommit, so a rolled-back write
 * never announces itself.
 *
 * @internal used by the write actions
 */
final class OwnerTransaction
{
    /**
     * @template TResult
     *
     * @param  Model&HasAttributes  $owner
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function run(Model $owner, Closure $callback): mixed
    {
        /** @var TResult $result */
        $result = $owner->attachedAttributes()->getRelated()->getConnection()->transaction($callback);

        return $result;
    }

    /**
     * As {@see run()}, with the owner's own row locked first, so whole-set writes of
     * one owner run one after another: each validates the set the previous one left.
     * The lock is taken by primary key (a record lock, no gap), on the owner's
     * connection — inside a transaction there too when that is not the attributes'
     * connection, so the lock is held until the write commits.
     *
     * @template TResult
     *
     * @param  Model&HasAttributes  $owner
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function runLocked(Model $owner, Closure $callback): mixed
    {
        $locked = static function () use ($owner, $callback): mixed {
            $owner->newQueryWithoutScopes()
                ->whereKey($owner->getKey())
                ->lockForUpdate()
                ->toBase()
                ->first([$owner->getKeyName()]);

            return $callback();
        };

        $connection = $owner->getConnection();

        /** @var TResult $result */
        $result = $connection->getName() === $owner->attachedAttributes()->getRelated()->getConnection()->getName()
            ? self::run($owner, $locked)
            : $connection->transaction(static fn (): mixed => self::run($owner, $locked));

        return $result;
    }
}
