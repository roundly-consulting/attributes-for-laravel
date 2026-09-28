<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;

/**
 * Dispatched once the write's transaction commits — never for a rolled-back write.
 */
final class AttributesSynced implements ShouldDispatchAfterCommit
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
