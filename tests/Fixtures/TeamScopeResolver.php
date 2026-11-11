<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\ResolvesUniqueScope;

/**
 * An invokable scope resolver: the owner's team key.
 */
final class TeamScopeResolver implements ResolvesUniqueScope
{
    public function __invoke(Model $owner): Model|int|string|null
    {
        $team = $owner->getAttribute('team_id');

        return is_int($team) || is_string($team) ? $team : null;
    }
}
