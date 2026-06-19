<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class AttributeDetached
{
    use Dispatchable;

    public function __construct(
        public Model $owner,
        public string $name,
    ) {}
}
