<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A single attribute write captured by AttributesFake.
 */
final readonly class RecordedWrite
{
    /**
     * @param  'set'|'forget'|'sync'|'meta'|'prune'  $verb
     * @param  Collection<string, mixed>|null  $meta
     * @param  array<string, mixed>  $attributes  the name => value map of a `sync`
     */
    public function __construct(
        public string $verb,
        public ?Model $owner = null,
        public ?string $name = null,
        public mixed $value = null,
        public ?Collection $meta = null,
        public array $attributes = [],
        public bool $forceDelete = false,
    ) {}

    /**
     * Whether this write is `$verb` on the owner (and, when given, the attribute name).
     */
    public function matches(string $verb, ?Model $owner = null, ?string $name = null): bool
    {
        return $this->verb === $verb
            && ($owner === null || ($this->owner !== null && $this->owner->is($owner)))
            && ($name === null || $this->name === $name);
    }
}
