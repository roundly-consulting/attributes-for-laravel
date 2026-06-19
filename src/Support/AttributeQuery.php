<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;

/**
 * Bulk read helper returned by Attributes::for($owner).
 *
 * A thin wrapper over the owner's getAttachedAttributes() so the read
 * logic lives in one place.
 */
final readonly class AttributeQuery
{
    /** @var Model&HasAttributes */
    private Model $owner;

    /**
     * @param  Model&HasAttributes  $owner
     */
    public function __construct(Model $owner)
    {
        $this->owner = $owner;
    }

    /**
     * @return Collection<string, mixed>
     */
    public function all(): Collection
    {
        return $this->owner->getAttachedAttributes();
    }

    /**
     * @return array<string, mixed>
     */
    public function toKeyValue(): array
    {
        return $this->all()->all();
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(static fn (mixed $key): string => (string) $key, array_keys($this->toKeyValue()));
    }

    public function get(string $name): mixed
    {
        return $this->owner->getAttachedAttributeValue($name);
    }

    public function has(string $name): bool
    {
        return $this->owner->hasAttachedAttribute($name);
    }
}
