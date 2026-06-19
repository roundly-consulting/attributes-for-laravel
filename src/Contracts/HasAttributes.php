<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Attributes\Models\Attribute;

interface HasAttributes
{
    /**
     * @return MorphMany<Attribute, *>
     */
    public function attachedAttributes(): MorphMany;
}
