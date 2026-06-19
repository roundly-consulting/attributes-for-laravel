<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Actions\AttachAttributeAction;
use RoundlyConsulting\Attributes\Actions\AttachAttributesAction;
use RoundlyConsulting\Attributes\Actions\DetachAttributesAction;
use RoundlyConsulting\Attributes\Actions\SyncAttributeMetaAction;
use RoundlyConsulting\Attributes\Actions\SyncAttributesAction;
use RoundlyConsulting\Attributes\Builders\AttributeWriter;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;

/**
 * @mixin Model
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements HasAttributesContract
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
     * Fluent builder for staging and persisting several attributes at once.
     */
    public function attributes(): AttributeWriter
    {
        return new AttributeWriter($this);
    }

    /**
     * @return Collection<string, mixed>
     */
    public function getAttachedAttributes(): Collection
    {
        $attributes = $this->relationLoaded('attachedAttributes')
            ? $this->loadedAttachedAttributes()
            : $this->attachedAttributes()->get();

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

    public function getAttachedAttributeValue(string $name): mixed
    {
        return $this->getAttachedAttribute($name)?->value;
    }

    public function getAttachedAttributeValueAsString(string $name): ?string
    {
        $attribute = $this->getAttachedAttribute($name);

        if ($attribute === null) {
            return null;
        }

        return new AttributeValueCaster()->toStorage($attribute->value, $attribute->type())->value;
    }

    public function attributeInt(string $name): ?int
    {
        $value = $this->getAttachedAttributeValue($name);

        return $value === null ? null : (int) $value;
    }

    public function attributeFloat(string $name): ?float
    {
        $value = $this->getAttachedAttributeValue($name);

        return $value === null ? null : (float) $value;
    }

    public function attributeBool(string $name): ?bool
    {
        $value = $this->getAttachedAttributeValue($name);

        return $value === null ? null : (bool) $value;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function attributeArray(string $name): ?array
    {
        $value = $this->getAttachedAttributeValue($name);

        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : [$value];
    }

    public function attributeDate(string $name): ?Carbon
    {
        $value = $this->getAttachedAttributeValue($name);

        if ($value === null) {
            return null;
        }

        return Carbon::parse(is_scalar($value) ? (string) $value : null);
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
    public function attachAttribute(string $name, mixed $value = null, ?Collection $meta = null): static
    {
        app(AttachAttributeAction::class)->execute($this, new AttributeData($name, $value, $meta));

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachAttributes(array $attributes): static
    {
        app(AttachAttributesAction::class)->execute($this, ...AttributeData::collection($attributes));

        return $this;
    }

    public function detachAttribute(string $name, bool $forceDelete = false): static
    {
        app(DetachAttributesAction::class)->execute($this, [$name], $forceDelete);

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function detachAttributes(array $attributes, bool $forceDelete = false): static
    {
        app(DetachAttributesAction::class)->execute($this, $attributes, $forceDelete);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function syncAttributes(array $attributes, bool $forceDelete = false): static
    {
        app(SyncAttributesAction::class)->execute($this, AttributeData::collection($attributes), $forceDelete);

        return $this;
    }

    /**
     * @param  Collection<string, mixed>|null  $meta
     */
    public function syncAttributeMeta(string $name, ?Collection $meta = null): static
    {
        app(SyncAttributeMetaAction::class)->execute($this, $name, $meta);

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function destroyAttributesExcept(array $attributes, bool $forceDelete = false): static
    {
        $query = $this->attachedAttributes()->whereNotIn('name', $attributes);

        $forceDelete ? $query->forceDelete() : $query->delete();

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function destroyAttributes(array $attributes, bool $forceDelete = false): static
    {
        app(DetachAttributesAction::class)->execute($this, $attributes, $forceDelete);

        return $this;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function scopeWhereAttribute(Builder $query, string $name, mixed $value): void
    {
        $stored = new AttributeValueCaster()->toStorage($value)->value;

        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name, $stored): void {
            /** @var Builder<Attribute> $sub */
            $sub->where('name', $name)->where('value', $stored);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<mixed>  $values
     */
    public function scopeWhereAttributeIn(Builder $query, string $name, array $values): void
    {
        $caster = new AttributeValueCaster;

        $stored = array_map(
            static fn (mixed $value): ?string => $caster->toStorage($value)->value,
            $values,
        );

        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name, $stored): void {
            /** @var Builder<Attribute> $sub */
            $sub->where('name', $name)->whereIn('value', $stored);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function scopeWhereHasAttribute(Builder $query, string $name): void
    {
        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name): void {
            /** @var Builder<Attribute> $sub */
            $sub->where('name', $name);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function scopeWhereDoesntHaveAttribute(Builder $query, string $name): void
    {
        $query->whereDoesntHave('attachedAttributes', function (Builder $sub) use ($name): void {
            /** @var Builder<Attribute> $sub */
            $sub->where('name', $name);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function scopeOrderByAttribute(Builder $query, string $name, string $direction = 'asc'): void
    {
        /** @var class-string<Attribute> $model */
        $model = config('attributes.model', Attribute::class);

        $related = new $model;
        $table = $related->getTable();

        $subQuery = $related->newQuery()->getQuery()
            ->select('value')
            ->from($table)
            ->whereColumn($table.'.owner_id', $query->getModel()->getQualifiedKeyName())
            ->where($table.'.owner_type', $query->getModel()->getMorphClass())
            ->where($table.'.name', $name)
            ->whereNull($table.'.deleted_at')
            ->limit(1);

        $query->orderBy($subQuery, $direction);
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
