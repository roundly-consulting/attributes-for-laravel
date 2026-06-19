<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Support\StoredAttributeValue;

interface HasAttributes
{
    /**
     * @return MorphMany<Attribute, *>
     */
    public function attachedAttributes(): MorphMany;

    /**
     * @return Collection<string, mixed>
     */
    public function getAttachedAttributes(): Collection;

    public function getAttachedAttributeValue(string $name): mixed;

    public function hasAttachedAttribute(string $name): bool;

    /**
     * A typed value object for a single attribute, with defaults applied.
     */
    public function attr(string $name): StoredAttributeValue;

    /**
     * Assert every required attribute is present and current values are valid.
     */
    public function validateAttributes(): void;
}
