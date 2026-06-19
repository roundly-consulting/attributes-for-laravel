<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Models\Attribute;

/**
 * @mixin Model
 */
trait HasAttributes
{
    /**
     * @return MorphMany<Attribute, $this>
     */
    public function attachedAttributes(): MorphMany
    {
        /** @var class-string<Attribute> $model */
        $model = config('attributes.model', Attribute::class);

        /** @var MorphMany<Attribute, $this> $relation */
        $relation = $this->morphMany($model, 'owner');

        return $relation;
    }

    /**
     * @return Collection<string, covariant string|null>
     */
    public function getAttachedAttributes(): Collection
    {
        $attributes = $this->relationLoaded('attachedAttributes')
            ? $this->loadedAttachedAttributes()
            : $this->attachedAttributes()->get(['name', 'value']);

        return $attributes
            ->mapWithKeys(fn (Attribute $attribute): array => [$attribute->name => $attribute->value])
            ->toBase();
    }

    public function hasAttachedAttribute(string $name): bool
    {
        if ($this->relationLoaded('attachedAttributes')) {
            return $this->loadedAttachedAttributes()->firstWhere('name', $name) !== null;
        }

        return $this->attachedAttributes()->where('name', $name)->exists();
    }

    public function getAttachedAttribute(string $name): ?Attribute
    {
        if ($this->relationLoaded('attachedAttributes')) {
            return $this->loadedAttachedAttributes()->firstWhere('name', $name);
        }

        return $this->attachedAttributes()->where('name', $name)->first();
    }

    public function getAttachedAttributeValue(string $name): ?string
    {
        return $this->getAttachedAttribute($name)?->value;
    }

    /**
     * @return Collection<string, mixed>|null
     */
    public function getAttachedAttributeMeta(string $name): ?Collection
    {
        return $this->getAttachedAttribute($name)?->meta;
    }

    /**
     * @param  Collection<string, mixed>|null  $meta
     */
    public function attachAttribute(string $name, string $value, ?Collection $meta = null): self
    {
        $this->attachedAttributes()->updateOrCreate(
            attributes: ['name' => $name],
            values: ['value' => $value, 'meta' => $meta],
        );

        return $this;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function attachAttributes(array $attributes): self
    {
        foreach ($attributes as $name => $value) {
            $this->attachAttribute($name, $value);
        }

        return $this;
    }

    public function detachAttribute(string $name, bool $forceDelete = false): self
    {
        $query = $this->attachedAttributes()->where('name', $name);

        $forceDelete ? $query->forceDelete() : $query->delete();

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function detachAttributes(array $attributes, bool $forceDelete = false): self
    {
        foreach ($attributes as $name) {
            $this->detachAttribute($name, $forceDelete);
        }

        return $this;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    public function syncAttributes(array $attributes, bool $forceDelete = false): self
    {
        $this->destroyAttributesExcept(array_keys($attributes), $forceDelete);
        $this->attachAttributes($attributes);

        return $this;
    }

    /**
     * @param  Collection<string, mixed>|null  $meta
     */
    public function syncAttributeMeta(string $name, ?Collection $meta = null): self
    {
        $this->attachedAttributes()->updateOrCreate(
            attributes: ['name' => $name],
            values: ['meta' => $meta],
        );

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function destroyAttributesExcept(array $attributes, bool $forceDelete = false): self
    {
        $query = $this->attachedAttributes()->whereNotIn('name', $attributes);

        $forceDelete ? $query->forceDelete() : $query->delete();

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function destroyAttributes(array $attributes, bool $forceDelete = false): self
    {
        $query = $this->attachedAttributes()->whereIn('name', $attributes);

        $forceDelete ? $query->forceDelete() : $query->delete();

        return $this;
    }

    /**
     * @return Collection<int, Attribute>
     */
    private function loadedAttachedAttributes(): Collection
    {
        /** @var Collection<int, Attribute> $loaded */
        $loaded = $this->getRelation('attachedAttributes');

        return $loaded;
    }
}
