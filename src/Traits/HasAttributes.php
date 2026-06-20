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
use RoundlyConsulting\Attributes\Exceptions\MissingRequiredAttributeException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;
use RoundlyConsulting\Attributes\Support\StoredAttributeValue;

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
     * Revisions recorded for this owner's attributes (newest first).
     *
     * @return MorphMany<AttributeRevision, $this>
     */
    public function attributeHistory(?string $name = null): MorphMany
    {
        /** @var MorphMany<AttributeRevision, $this> $relation */
        $relation = $this->morphMany(AttributeRevision::class, 'owner')->latest('id');

        if ($name !== null) {
            $relation->where('name', $name);
        }

        return $relation;
    }

    /**
     * Read the recorded revisions, optionally filtered by attribute name.
     *
     * @return Collection<int, AttributeRevision>
     */
    public function history(?string $name = null): Collection
    {
        return $this->attributeHistory($name)->get();
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
        $value = $this->getAttachedAttribute($name)?->value;

        return $value ?? $this->defaultFor($name);
    }

    public function getAttachedAttributeValueAsString(string $name): ?string
    {
        $attribute = $this->getAttachedAttribute($name);

        if ($attribute === null) {
            return null;
        }

        return new AttributeValueCaster()->toStorage($attribute->value, $attribute->type())->value;
    }

    /**
     * A typed value object for a single attribute, with defaults applied.
     */
    public function attr(string $name): StoredAttributeValue
    {
        return new StoredAttributeValue($this->getAttachedAttributeValue($name));
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
     * Assert every required attribute is present and current values are valid.
     *
     * @throws MissingRequiredAttributeException
     */
    public function validateAttributes(): void
    {
        $registry = app(AttributeRegistry::class);

        foreach ($registry->definitionsFor($this) as $definition) {
            $hasValue = $this->hasAttachedAttribute($definition->name);

            if ($definition->required && ! $hasValue) {
                throw MissingRequiredAttributeException::forName($definition->name);
            }

            if ($hasValue) {
                $registry->validateFor($this, $definition->name, $this->getAttachedAttributeValue($definition->name));
            }
        }
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
     * Filter owners whose attribute value falls within the (inclusive) bounds.
     *
     * Bounds are cast to their storage string and compared. Reliable for
     * ISO-8601 datetimes and strings; integer ranges are zero-pad-sensitive.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeWhereAttributeBetween(Builder $query, string $name, mixed $min, mixed $max): void
    {
        $caster = new AttributeValueCaster;

        $low = $caster->toStorage($min)->value;
        $high = $caster->toStorage($max)->value;

        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name, $low, $high): void {
            /** @var Builder<Attribute> $sub */
            $sub->where('name', $name)->whereBetween('value', [$low, $high]);
        });
    }

    /**
     * Filter owners with the attribute present but its value SQL NULL.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeWhereAttributeNull(Builder $query, string $name): void
    {
        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name): void {
            /** @var Builder<Attribute> $sub */
            $sub->where('name', $name)->whereNull('value');
        });
    }

    /**
     * Filter owners with the attribute present and its value not SQL NULL.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeWhereAttributeNotNull(Builder $query, string $name): void
    {
        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name): void {
            /** @var Builder<Attribute> $sub */
            $sub->where('name', $name)->whereNotNull('value');
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

        $query->orderBy($subQuery, $this->orderDirection($direction));
    }

    /**
     * Normalise a sort direction to a value Laravel's query builder accepts.
     *
     * @return 'asc'|'desc'
     */
    private function orderDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * The default value declared for a name, honouring per-model definitions.
     */
    private function defaultFor(string $name): mixed
    {
        return app(AttributeRegistry::class)->default($name, $this);
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
