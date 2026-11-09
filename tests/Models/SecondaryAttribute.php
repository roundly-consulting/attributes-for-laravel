<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests\Models;

use RoundlyConsulting\Attributes\Models\Attribute;

/**
 * A host's attribute model on its own connection — the owner's row lives elsewhere.
 */
final class SecondaryAttribute extends Attribute
{
    protected $connection = 'secondary';
}
