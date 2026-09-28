<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Attributes\Models\Attribute;

/**
 * Dispatched once the write's transaction commits — never for a rolled-back write.
 */
final class AttributeAttached implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Model $owner,
        public Attribute $attribute,
    ) {}
}
