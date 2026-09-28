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
}
