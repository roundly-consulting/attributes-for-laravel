<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;
use RoundlyConsulting\Testing\Fixtures\Concerns\RecordsLocks;

/**
 * A Product that records every pessimistic lock taken on its own row (and at which
 * transaction depth) — SQLite compiles `FOR UPDATE` away, so the lock is observed here.
 */
final class LockingProduct extends Model implements HasAttributesContract
{
    use HasAttributes;
    use RecordsLocks;

    protected $table = 'products';

    protected $guarded = [];
}
