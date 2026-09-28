<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Actions\AttachAttributeAction;
use RoundlyConsulting\Attributes\Actions\AttachAttributesAction;
use RoundlyConsulting\Attributes\Actions\DetachAttributesAction;
use RoundlyConsulting\Attributes\Actions\DetachAttributesExceptAction;
use RoundlyConsulting\Attributes\Actions\SyncAttributeMetaAction;
use RoundlyConsulting\Attributes\Actions\SyncAttributesAction;
use RoundlyConsulting\Attributes\Builders\AttributeWriter;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Models\AttributeRevision;

/**
 * One owner's attributes, returned by `Attributes::for($owner)`: reads, and
 * writes that resolve their action from the container (so host overrides and
 * `Attributes::fake()` apply). Every write goes through this handle — the
 * `HasAttributes` trait and the staged writer included.
 *
 * Not final: the recording fake extends it.
 */
readonly class OwnerAttributes
{
    /**
     * @param  Model&HasAttributes  $owner
     */
    public function __construct(
        protected Container $container,
        protected Model $owner,
    ) {}

    // Reads -------------------------------------------------------------------

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

    /**
     * The value (or its declared default when the attribute is not attached).
     */
    public function get(string $name): mixed
    {
        return $this->owner->getAttachedAttributeValue($name);
    }

    public function has(string $name): bool
    {
        return $this->owner->hasAttachedAttribute($name);
    }

    /**
     * Recorded revisions (newest first), optionally for one attribute. Empty
     * unless `attributes.history.enabled` is on.
     *
     * @return Collection<int, AttributeRevision>
     */
    public function history(?string $name = null): Collection
    {
        return AttributeRevision::query()
            ->where('owner_type', $this->owner->getMorphClass())
            ->where('owner_id', $this->owner->getKey())
            ->when($name !== null, static fn ($query) => $query->where('name', $name))
            ->latest('id')
            ->get()
            ->toBase();
    }

    // Writes ------------------------------------------------------------------

    /**
     * Attach (or update) one attribute, validated against its definition and
     * stored in its type. The stored meta is kept unless `$meta` is given.
     *
     * @param  array<string, mixed>|Collection<string, mixed>|null  $meta
     */
    public function set(string $name, mixed $value = null, array|Collection|null $meta = null): Attribute
    {
        return $this->container->make(AttachAttributeAction::class)
            ->execute($this->owner, new AttributeData($name, $value, $this->toMeta($meta)));
    }

    /**
     * Attach (or update) several attributes, keeping any others — all or
     * nothing: every value is validated before the first write, and the writes
     * share one transaction. `$meta` is an optional per-name meta map.
     *
     * @param  array<string, mixed>  $attributes  name => value
     * @param  array<string, array<string, mixed>|Collection<string, mixed>>  $meta  name => meta
     * @return Collection<int, Attribute>
     */
    public function setMany(array $attributes, array $meta = []): Collection
    {
        return $this->container->make(AttachAttributesAction::class)
            ->execute($this->owner, ...$this->toData($attributes, $meta));
    }

    /**
     * Make the owner's attributes exactly this set: attach/update the given
     * ones and detach every other — all or nothing, like `setMany()`.
     *
     * @param  array<string, mixed>  $attributes  name => value
     * @param  array<string, array<string, mixed>|Collection<string, mixed>>  $meta  name => meta
     * @return Collection<int, Attribute>
     */
    public function sync(array $attributes, bool $forceDelete = false, array $meta = []): Collection
    {
        return $this->container->make(SyncAttributesAction::class)
            ->execute($this->owner, $this->toData($attributes, $meta), $forceDelete);
    }

    /**
     * Detach attributes by name (soft delete unless `$forceDelete`). Returns
     * how many were removed.
     *
     * @param  list<string>|string  $names
     */
    public function forget(array|string $names, bool $forceDelete = false): int
    {
        return $this->container->make(DetachAttributesAction::class)
            ->execute($this->owner, is_string($names) ? [$names] : $names, $forceDelete);
    }

    /**
     * Detach every attribute except the given names (with `$forceDelete`,
     * previously soft-deleted extras are purged too). Returns the names detached.
     *
     * @param  list<string>  $keep
     * @return list<string>
     */
    public function forgetExcept(array $keep, bool $forceDelete = false): array
    {
        return $this->container->make(DetachAttributesExceptAction::class)
            ->execute($this->owner, $keep, $forceDelete);
    }

    /**
     * Replace one attribute's meta — `null` clears it — attaching a null-valued
     * attribute when it is not attached yet. Strict mode, history and the
     * AttributeAttached event apply as for a value write.
     *
     * @param  array<string, mixed>|Collection<string, mixed>|null  $meta
     */
    public function meta(string $name, array|Collection|null $meta): Attribute
    {
        return $this->container->make(SyncAttributeMetaAction::class)
            ->execute($this->owner, $name, $this->toMeta($meta));
    }

    /**
     * Stage several attributes (with meta) and persist them in one call:
     * `->stage()->set('color', 'red')->meta('color', [...])->save()`.
     */
    public function stage(): AttributeWriter
    {
        return new AttributeWriter($this, $this->owner);
    }

    /**
     * @param  array<string, mixed>|Collection<string, mixed>|null  $meta
     * @return Collection<string, mixed>|null
     */
    protected function toMeta(array|Collection|null $meta): ?Collection
    {
        return is_array($meta) ? new Collection($meta) : $meta;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array<string, mixed>|Collection<string, mixed>>  $meta
     * @return list<AttributeData>
     */
    protected function toData(array $attributes, array $meta): array
    {
        $data = [];

        foreach ($attributes as $name => $value) {
            $data[] = new AttributeData((string) $name, $value, $this->toMeta($meta[$name] ?? null));
        }

        return $data;
    }
}
