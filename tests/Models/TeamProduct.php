<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

/**
 * A product that belongs to a team — the owner of the `unique => scope` tests.
 */
final class TeamProduct extends Model implements HasAttributesContract
{
    use HasAttributes;

    protected $table = 'products';

    protected $guarded = [];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
