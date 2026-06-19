<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;

final class AttributesSynced
{
    use Dispatchable;

    /**
     * @param  list<AttributeData>  $attributes
     */
    public function __construct(
        public Model $owner,
        public array $attributes,
    ) {}
}
