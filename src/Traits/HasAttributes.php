<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\AttributesManager;
use RoundlyConsulting\Attributes\Builders\AttributeWriter;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\DataTransferObjects\StoredValue;
use RoundlyConsulting\Attributes\Exceptions\MissingRequiredAttributeException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Support\AttributeModel;
use RoundlyConsulting\Attributes\Support\AttributesConfig;
use RoundlyConsulting\Attributes\Support\AttributeValueCaster;
use RoundlyConsulting\Attributes\Support\StoredAttributeValue;
use RoundlyConsulting\Attributes\Support\TypedValueQuery;

/**
 * Every write delegates to `Attributes::for($this)` (the AttributesManager), so
 * host overrides and `Attributes::fake()` see trait calls too.
 *
 * @mixin Model
 *
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements HasAttributesContract
 */
trait HasAttributes
{
    /**
     * A permanently deleted owner (no SoftDeletes, or forceDelete()) takes its
     * attribute rows with it, freeing its unique values — through the manager,
     * so detached revisions, AttributeDetached and the fake see it. Soft-deleting
     * the owner keeps them. Off with `attributes.delete_with_owner`.
     */
    public static function bootHasAttributes(): void
    {
        static::deleted(static function (self $owner): void {
            if (! AttributesConfig::deleteWithOwner()) {
                return;
            }

            if (method_exists($owner, 'isForceDeleting') && ! $owner->isForceDeleting()) {
                return;
            }

            $names = array_values($owner->attachedAttributes()->withTrashed()->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->unique()
                ->all());

            if ($names !== []) {
                app(AttributesManager::class)->for($owner)->forget($names, forceDelete: true);
            }
        });
    }

    /**
     * @return MorphMany<Attribute, $this>
     */
    public function attachedAttributes(): MorphMany
    {
        /** @var MorphMany<Attribute, $this> $relation */
        $relation = $this->morphMany(AttributeModel::class(), 'owner');

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
        return app(AttributesManager::class)->for($this)->stage();
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

    /**
     * The stored value — or the declared default when the attribute is not
     * attached. An attached attribute holding `null` reads as `null`.
     */
    public function getAttachedAttributeValue(string $name): mixed
    {
        $attribute = $this->getAttachedAttribute($name);

        return $attribute === null ? $this->defaultFor($name) : $attribute->value;
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
        return $this->attr($name)->date();
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
        app(AttributesManager::class)->for($this)->set($name, $value, $meta);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachAttributes(array $attributes): static
    {
        app(AttributesManager::class)->for($this)->setMany($attributes);

        return $this;
    }

    public function detachAttribute(string $name, bool $forceDelete = false): static
    {
        app(AttributesManager::class)->for($this)->forget($name, $forceDelete);

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function detachAttributes(array $attributes, bool $forceDelete = false): static
    {
        app(AttributesManager::class)->for($this)->forget($attributes, $forceDelete);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function syncAttributes(array $attributes, bool $forceDelete = false): static
    {
        app(AttributesManager::class)->for($this)->sync($attributes, $forceDelete);

        return $this;
    }

    /**
     * @param  Collection<string, mixed>|null  $meta
     */
    public function syncAttributeMeta(string $name, ?Collection $meta = null): static
    {
        app(AttributesManager::class)->for($this)->meta($name, $meta);

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function destroyAttributesExcept(array $attributes, bool $forceDelete = false): static
    {
        app(AttributesManager::class)->for($this)->forgetExcept($attributes, $forceDelete);

        return $this;
    }

    /**
     * @param  list<string>  $attributes
     */
    public function destroyAttributes(array $attributes, bool $forceDelete = false): static
    {
        app(AttributesManager::class)->for($this)->forget($attributes, $forceDelete);

        return $this;
    }

    /**
     * Filter owners whose attribute equals the value — a typed comparison: the
     * value is taken in the name's defined type (else its own), integers and
     * floats match each other, and other types match only themselves.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeWhereAttribute(Builder $query, string $name, mixed $value): void
    {
        $needle = TypedValueQuery::needle($query->getModel(), $name, $value);

        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name, $needle): void {
            /** @var Builder<Attribute> $sub */
            $sub->where($sub->qualifyColumn('name'), $name);

            TypedValueQuery::whereEquals($sub, $needle);
        });
    }

    /**
     * Filter owners whose attribute equals any of the values (typed, as
     * {@see scopeWhereAttribute()}; a `null` in the list matches a stored null).
     *
     * @param  Builder<Model>  $query
     * @param  list<mixed>  $values
     */
    public function scopeWhereAttributeIn(Builder $query, string $name, array $values): void
    {
        $owner = $query->getModel();

        $needles = array_map(
            static fn (mixed $value): StoredValue => TypedValueQuery::needle($owner, $name, $value),
            $values,
        );

        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name, $needles): void {
            /** @var Builder<Attribute> $sub */
            $sub->where($sub->qualifyColumn('name'), $name);

            if ($needles === []) {
                $sub->whereIn($sub->qualifyColumn('value'), []);

                return;
            }

            $sub->where(function (Builder $any) use ($needles): void {
                foreach ($needles as $needle) {
                    $any->orWhere(static function (Builder $one) use ($needle): void {
                        /** @var Builder<Attribute> $one */
                        TypedValueQuery::whereEquals($one, $needle);
                    });
                }
            });
        });
    }

    /**
     * Filter owners whose attribute value falls within the (inclusive) bounds.
     *
     * Numeric bounds (in the name's defined type, else their own) compare
     * numerically against integer and float values; any other bounds compare as
     * storage text — chronological for datetimes, which are stored in UTC.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeWhereAttributeBetween(Builder $query, string $name, mixed $min, mixed $max): void
    {
        $low = TypedValueQuery::needle($query->getModel(), $name, $min);
        $high = TypedValueQuery::needle($query->getModel(), $name, $max);

        $query->whereHas('attachedAttributes', function (Builder $sub) use ($name, $low, $high): void {
            /** @var Builder<Attribute> $sub */
            $sub->where($sub->qualifyColumn('name'), $name);

            if ($low->value !== null && $high->value !== null && $low->type->isNumeric() && $high->type->isNumeric()) {
                TypedValueQuery::whereNumericBetween($sub, $low->value, $high->value);

                return;
            }

            $sub->whereBetween($sub->qualifyColumn('value'), [$low->value, $high->value]);
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
     * Order owners by an attribute: integer and float values sort numerically,
     * every other type by its storage text (chronological for datetimes).
     * Encrypted values have no meaningful order.
     *
     * @param  Builder<Model>  $query
     */
    public function scopeOrderByAttribute(Builder $query, string $name, string $direction = 'asc'): void
    {
        $model = AttributeModel::class();

        $related = new $model;
        $table = $related->getTable();

        $value = fn (): QueryBuilder => $related->newQuery()->getQuery()
            ->from($table)
            ->whereColumn($table.'.owner_id', $query->getModel()->getQualifiedKeyName())
            ->where($table.'.owner_type', $query->getModel()->getMorphClass())
            ->where($table.'.name', $name)
            ->whereNull($table.'.deleted_at')
            ->limit(1);

        $numeric = $value();
        $numeric->select(TypedValueQuery::numeric($numeric, $table));

        $query
            ->orderBy($numeric, $this->orderDirection($direction))
            ->orderBy($value()->select($table.'.value'), $this->orderDirection($direction));
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
