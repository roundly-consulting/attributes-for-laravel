<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Actions\AttachAttributesAction;
use RoundlyConsulting\Attributes\Actions\SyncAttributesAction;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;

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
        $data = $this->stagedData();

        if ($data !== []) {
            app(AttachAttributesAction::class)->execute($this->owner, ...$data);
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
        app(SyncAttributesAction::class)->execute($this->owner, $this->stagedData(), $forceDelete);

        return $this->owner;
    }

    /**
     * @return list<AttributeData>
     */
    private function stagedData(): array
    {
        $data = [];

        foreach ($this->values as $name => $value) {
            $data[] = new AttributeData($name, $value, $this->meta[$name] ?? null);
        }

        return $data;
    }
}
