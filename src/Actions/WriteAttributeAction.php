<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\DataTransferObjects\RevisionData;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Events\AttributeAttached;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

/**
 * The raw upsert behind every attach, sync and meta write: one row per owner +
 * name (a soft-deleted row is restored, never duplicated), then the revision and
 * the event. It does not validate — callers run AttributeRegistry::assertWritable()
 * first — and it runs inside the caller's transaction. The owner must be saved: a
 * write to an unsaved (or deleted) owner throws instead of inserting a keyless row.
 *
 * Two races are settled by the database rather than by the read before the write:
 * a concurrent insert of the same owner + name turns this write into an update of
 * the winner's row, and a concurrent writer of the same unique value surfaces as
 * DuplicateAttributeValueException.
 *
 * The lookup never takes a lock on a row that does not exist: on InnoDB (REPEATABLE
 * READ) a locking read that finds nothing takes a gap lock, and two such gap locks
 * block each other's insert — a deadlock between concurrent first writes, even of
 * different owners. So an existing row is found with a plain read and then locked by
 * its primary key, and a missing one is inserted straight away.
 *
 * @internal building block of the attach, sync and meta actions
 */
final readonly class WriteAttributeAction
{
    public function __construct(
        private AttributeRegistry $registry,
        private RecordAttributeRevisionAction $recordRevision,
    ) {}

    /**
     * Write the value (and the meta, when given) — or, with `$metaOnly`, replace
     * only the meta and leave the stored value alone.
     *
     * @param  Model&HasAttributes  $owner
     */
    public function execute(Model $owner, AttributeData $data, bool $metaOnly = false): Attribute
    {
        if (! $owner->exists || $owner->getKey() === null) {
            throw AttributesException::unsavedOwner($data->name, $owner);
        }

        $attribute = $this->findAndLock($owner, $data->name) ?? $this->insertOrFindWinner($owner, $data, $metaOnly);

        if ($attribute->wasRecentlyCreated) {
            return $this->announce($owner, $attribute, null);
        }

        $before = $attribute->trashed() ? null : clone $attribute;

        $this->fill($owner, $attribute, $data, $metaOnly, fresh: $before === null);

        try {
            $attribute->save();
        } catch (UniqueConstraintViolationException $exception) {
            // Owner + name never change on an update, so it was the unique value index.
            throw $this->duplicate($owner, $data->name, $exception);
        }

        return $this->announce($owner, $attribute, $before);
    }

    /**
     * The owner's row for the name, locked — or null when there is none to lock.
     *
     * A plain read finds it; the lock is then taken on its primary key, which locks
     * that one record and no gap. A row deleted in between is simply not there: the
     * write inserts instead.
     *
     * @param  Model&HasAttributes  $owner
     */
    private function findAndLock(Model $owner, string $name): ?Attribute
    {
        $found = $owner->attachedAttributes()->withTrashed()->where('name', $name)->first();

        if ($found === null) {
            return null;
        }

        return $owner->attachedAttributes()->withTrashed()->whereKey($found->getKey())->lockForUpdate()->first();
    }

    /**
     * The row a concurrent writer just committed, locked. Only called after the
     * insert hit a unique index, so the row exists when it was the owner + name
     * index — a locking read is needed here, since a plain read on REPEATABLE READ
     * would not see a row committed after the transaction's snapshot.
     *
     * @param  Model&HasAttributes  $owner
     */
    private function lockWinner(Model $owner, string $name): ?Attribute
    {
        return $owner->attachedAttributes()->withTrashed()->where('name', $name)->lockForUpdate()->first();
    }

    /**
     * Insert the row — or, when a concurrent writer inserted the same owner + name
     * first, return that (now locked) row so this write updates it.
     *
     * @param  Model&HasAttributes  $owner
     */
    private function insertOrFindWinner(Model $owner, AttributeData $data, bool $metaOnly): Attribute
    {
        $attribute = $owner->attachedAttributes()->make(['name' => $data->name]);

        $this->fill($owner, $attribute, $data, $metaOnly, fresh: true);

        try {
            // A savepoint, so losing the race leaves the surrounding transaction usable.
            $attribute->getConnection()->transaction(static fn (): bool => $attribute->save());
        } catch (UniqueConstraintViolationException $exception) {
            // No row for this owner + name means the unique value index fired instead.
            return $this->lockWinner($owner, $data->name) ?? throw $this->duplicate($owner, $data->name, $exception);
        }

        return $attribute;
    }

    /**
     * @param  Model&HasAttributes  $owner
     */
    private function fill(Model $owner, Attribute $attribute, AttributeData $data, bool $metaOnly, bool $fresh): void
    {
        // The value cast resolves the definition (type, encryption, uniqueness)
        // through the owner, so the relation must be set before the value is.
        $attribute->setRelation('owner', $owner);

        if ($fresh) {
            // New, or a detached row coming back: it starts over, old meta is not revived.
            $attribute->setAttribute($attribute->getDeletedAtColumn(), null);
            $attribute->forceFill(['value' => $metaOnly ? null : $data->value, 'meta' => $data->meta]);

            return;
        }

        if (! $metaOnly) {
            $attribute->forceFill(['value' => $data->value]);
        }

        // A value write keeps the stored meta unless new meta is given; meta() always replaces it.
        if ($metaOnly || $data->meta !== null) {
            $attribute->forceFill(['meta' => $data->meta]);
        }
    }

    /**
     * @param  Model&HasAttributes  $owner
     */
    private function announce(Model $owner, Attribute $attribute, ?Attribute $before): Attribute
    {
        if ($this->recordRevision->enabled()) {
            $this->recordRevision->execute($owner, new RevisionData(
                name: $attribute->name,
                type: $before === null ? RevisionType::Attached : RevisionType::Updated,
                oldValue: $before === null ? null : self::raw($before),
                newValue: self::raw($attribute),
                oldValueType: $before?->type(),
                newValueType: $attribute->type(),
                oldMeta: $before?->meta,
                newMeta: $attribute->meta,
            ));
        }

        AttributeAttached::dispatch($owner, $attribute);

        // Reads prefer an eager-loaded relation; drop it so the next read sees this write.
        $owner->unsetRelation('attachedAttributes');

        return $attribute;
    }

    private function duplicate(Model $owner, string $name, UniqueConstraintViolationException $exception): DuplicateAttributeValueException
    {
        return DuplicateAttributeValueException::forName(
            $name,
            $this->registry->resolveFor($owner, $name)->unique ?? UniqueScope::Global_,
            $exception,
        );
    }

    private static function raw(Attribute $attribute): ?string
    {
        $raw = $attribute->getRawOriginal('value');

        return $raw === null ? null : (string) $raw;
    }
}
