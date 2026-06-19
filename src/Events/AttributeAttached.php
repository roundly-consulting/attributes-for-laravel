<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Attributes\Models\Attribute;

final class AttributeAttached
{
    use Dispatchable;

    public function __construct(
        public Model $owner,
        public Attribute $attribute,
    ) {}
}
