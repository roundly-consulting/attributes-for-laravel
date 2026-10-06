<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Testing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\OwnerAttributes;

/**
 * The owner handle under `Attributes::fake()`: every write passes through to
 * the real action and is recorded once it succeeds. `setMany()` (and so
 * `stage()->save()`) records one `set` per attribute; `forget()` and
 * `forgetExcept()` record one `forget` per attribute they removed — a name that
 * was not attached is not recorded, as the real forget() does nothing for it.
 */
final readonly class RecordingOwnerAttributes extends OwnerAttributes
{
    /**
     * @param  Model&HasAttributes  $owner
     */
    public function __construct(
        private AttributesFake $fake,
        Container $container,
        Model $owner,
    ) {
        parent::__construct($container, $owner);
    }

    public function set(string $name, mixed $value = null, array|Collection|null $meta = null): Attribute
    {
        $attribute = parent::set($name, $value, $meta);

        $this->fake->record(new RecordedWrite('set', $this->owner, $name, $value, $this->toMeta($meta)));

        return $attribute;
    }

    public function setMany(array $attributes, array $meta = []): Collection
    {
        $written = parent::setMany($attributes, $meta);

        foreach ($attributes as $name => $value) {
            $this->fake->record(new RecordedWrite('set', $this->owner, (string) $name, $value, $this->toMeta($meta[$name] ?? null)));
        }

        return $written;
    }

    public function sync(array $attributes, bool $forceDelete = false, array $meta = []): Collection
    {
        $written = parent::sync($attributes, $forceDelete, $meta);

        $this->fake->record(new RecordedWrite('sync', $this->owner, attributes: $attributes, forceDelete: $forceDelete));

        return $written;
    }

    public function forget(array|string $names, bool $forceDelete = false): int
    {
        $names = is_string($names) ? [$names] : $names;

        // Only names that are attached get removed (and announced) by the real forget();
        // a name that never was is a no-op there, so it is not recorded here either.
        $attached = $names === [] ? [] : $this->owner->attachedAttributes()
            ->whereIn('name', $names)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        $count = parent::forget($names, $forceDelete);

        foreach (array_unique(array_intersect($names, $attached)) as $name) {
            $this->fake->record(new RecordedWrite('forget', $this->owner, $name, forceDelete: $forceDelete));
        }

        return $count;
    }

    public function forgetExcept(array $keep, bool $forceDelete = false): array
    {
        $names = parent::forgetExcept($keep, $forceDelete);

        foreach ($names as $name) {
            $this->fake->record(new RecordedWrite('forget', $this->owner, $name, forceDelete: $forceDelete));
        }

        return $names;
    }

    public function meta(string $name, array|Collection|null $meta): Attribute
    {
        $attribute = parent::meta($name, $meta);

        $this->fake->record(new RecordedWrite('meta', $this->owner, $name, meta: $this->toMeta($meta)));

        return $attribute;
    }
}
