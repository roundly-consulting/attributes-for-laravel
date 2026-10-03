<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Casts\AttributeValue;
use RoundlyConsulting\Attributes\Database\Factories\AttributeFactory;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Support\AttributeCollection;
use RoundlyConsulting\Attributes\Support\AttributeModel;
use RoundlyConsulting\Attributes\Support\AttributesConfig;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $name
 * @property mixed $value
 * @property string|null $value_type
 * @property bool $is_encrypted
 * @property string|null $unique_hash
 * @property-read AttributeType $type
 * @property Collection<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * Not final on purpose: `attributes.model` documents swapping in a host model
 * that extends this one.
 */
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * The unique index's bookkeeping column — a blind index for encrypted values —
     * is never part of a serialized attribute.
     *
     * @var list<string>
     */
    protected $hidden = ['unique_hash'];

    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        return AttributesConfig::table();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The resolved value type of this attribute.
     */
    public function type(): AttributeType
    {
        $type = $this->value_type;

        return $type === null ? AttributeType::String_ : AttributeType::tryFrom($type) ?? AttributeType::String_;
    }

    /**
     * @param  Builder<Attribute>  $query
     */
    public function scopeForName(Builder $query, string $name): void
    {
        $query->where('name', $name);
    }

    /**
     * @param  Builder<Attribute>  $query
     */
    public function scopeForOwner(Builder $query, Model $owner): void
    {
        $query
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }

    /**
     * @param  Builder<Attribute>  $query
     */
    public function scopeOfType(Builder $query, AttributeType $type): void
    {
        $query->where('value_type', $type->value);
    }

    /**
     * Every attribute query and relation returns the richer collection.
     *
     * @param  array<int, static>  $models
     * @return AttributeCollection<static>
     */
    public function newCollection(array $models = []): AttributeCollection
    {
        return new AttributeCollection($models);
    }

    /**
     * Load every attribute as the richer collection.
     *
     * Resolves through the `attributes.model` seam rather than `static::`. Late static
     * binding would resolve to the *called* class, so a host that swapped the model and
     * called the documented `Attribute::collect()` got rows hydrated as the packaged class
     * — none of their casts, scopes or model events. Every other call site in the package
     * already went through AttributeModel; this one did not.
     *
     * @return AttributeCollection<Attribute>
     */
    public static function collect(): AttributeCollection
    {
        /** @var AttributeCollection<Attribute> $collection */
        $collection = AttributeModel::class()::query()->get();

        return $collection;
    }

    /**
     * A soft-deleted value gives up its unique slot, exactly as the pre-check never
     * counted trashed rows — so a host calling `$attribute->delete()` directly frees
     * the value too. (A host subclass overriding `booted()` calls `parent::booted()`.)
     */
    protected static function booted(): void
    {
        static::softDeleted(static function (Attribute $attribute): void {
            if ($attribute->unique_hash === null) {
                return;
            }

            $attribute->newQueryWithoutScopes()->whereKey($attribute->getKey())->update(['unique_hash' => null]);
            $attribute->unique_hash = null;
            $attribute->syncOriginalAttribute('unique_hash');
        });
    }

    protected static function newFactory(): AttributeFactory
    {
        return AttributeFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
            'is_encrypted' => 'boolean',
            'value' => AttributeValue::class,
        ];
    }
}
