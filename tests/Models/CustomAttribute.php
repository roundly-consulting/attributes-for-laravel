<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * The host model `attributes.model` invites, used to prove the seam is real.
 *
 * `CountsCreations` is what makes the proof independent of `instanceof`: it counts rows
 * created as *this exact class*, so an attribute row created as the packaged Attribute —
 * which would still satisfy an `instanceof` check while firing none of the host's model
 * events (permissions #31) — cannot be mistaken for an honoured swap.
 *
 * No `$table` override: `Attribute::getTable()` already resolves `attributes.table`, and
 * the subclass inherits that.
 */
final class CustomAttribute extends Attribute
{
    use CountsCreations;
}
