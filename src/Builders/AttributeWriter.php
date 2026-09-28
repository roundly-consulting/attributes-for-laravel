<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\OwnerAttributes;

/**
 * Stages several attributes (and their meta) for one owner, then persists them
 * through the owner handle it came from — `Attributes::for($owner)->stage()` or
 * `$owner->attributes()` — so the fake and host overrides see the write.
 */
final class AttributeWriter
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * @var array<string, Collection<string, mixed>>
     */
    private array $meta = [];

    /**
     * @param  Model&HasAttributes  $owner
     */
    public function __construct(
        private readonly OwnerAttributes $attributes,
        private readonly Model $owner,
    ) {}

    public function set(string $name, mixed $value): self
    {
        $this->values[$name] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Collection<string, mixed>  $meta
     */
    public function meta(string $name, array|Collection $meta): self
    {
        $this->meta[$name] = $meta instanceof Collection ? $meta : new Collection($meta);

        return $this;
    }

    public function forget(string ...$names): self
    {
        foreach ($names as $name) {
            unset($this->values[$name], $this->meta[$name]);
        }

        return $this;
    }

    /**
     * Keep only the given staged attribute names.
     */
    public function only(string ...$names): self
    {
        $this->values = array_intersect_key($this->values, array_flip($names));
        $this->meta = array_intersect_key($this->meta, array_flip($names));

        return $this;
    }

    /**
     * Drop the given staged attribute names.
     */
    public function except(string ...$names): self
    {
        return $this->forget(...$names);
    }

    /**
     * Persist the staged attributes, keeping any others already attached.
     *
     * @return Model&HasAttributes
     */
    public function save(): Model
    {
        if ($this->values !== []) {
            $this->attributes->setMany($this->values, $this->stagedMeta());
        }

        return $this->owner;
    }

    /**
     * Persist the staged attributes and remove any others not staged.
     *
     * @return Model&HasAttributes
     */
    public function sync(bool $forceDelete = false): Model
    {
        $this->attributes->sync($this->values, $forceDelete, $this->stagedMeta());

        return $this->owner;
    }

    /**
     * Meta for staged values only — meta staged for a name with no value is
     * not persisted (as before).
     *
     * @return array<string, Collection<string, mixed>>
     */
    private function stagedMeta(): array
    {
        return array_intersect_key($this->meta, $this->values);
    }
}
