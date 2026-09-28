<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Attributes\AttributesManager;
use RoundlyConsulting\Attributes\OwnerAttributes;

/**
 * A pass-through AttributesManager that records every attribute write so tests
 * can assert against intent (in the spirit of Bus::fake()). Real reads and
 * writes still run, so validation, defaults and history behave normally.
 *
 * Everything funnels through it — the facade, an injected AttributesManager,
 * the owner handle, the staged writer and every `HasAttributes` trait write.
 * A write is recorded once it succeeds; one that throws records nothing.
 * Definition calls (`define()`, `flush()` …) are in-memory configuration, not
 * writes: they go straight to the real registry and are not recorded.
 *
 * Lives in src/ so host apps can use it; it depends on PHPUnit's Assert, which
 * is always present in a Laravel app's dev dependencies.
 */
final class AttributesFake extends AttributesManager
{
    /** @var list<RecordedWrite> */
    private array $recorded = [];

    public function for(Model $owner): OwnerAttributes
    {
        return new RecordingOwnerAttributes($this, $this->container, $owner);
    }

    public function prune(?int $days = null): int
    {
        $count = parent::prune($days);

        $this->record(new RecordedWrite('prune'));

        return $count;
    }

    /**
     * @internal called by the recording owner handle
     */
    public function record(RecordedWrite $write): void
    {
        $this->recorded[] = $write;
    }

    /** @return list<RecordedWrite> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function assertNothingWritten(): void
    {
        Assert::assertSame([], $this->recorded, sprintf(
            'Failed asserting that no attribute write was recorded; recorded [%s].',
            implode(', ', array_map(static fn (RecordedWrite $write): string => $write->verb, $this->recorded)),
        ));
    }

    /**
     * Assert an attribute was set on the owner — with exactly `$value` when a
     * third argument is passed (null included).
     */
    public function assertSet(Model $owner, string $name, mixed $value = null): void
    {
        $checkValue = func_num_args() >= 3;

        Assert::assertTrue(
            $this->any(static fn (RecordedWrite $write): bool => $write->matches('set', $owner, $name)
                && (! $checkValue || $write->value === $value)),
            $checkValue
                ? "Failed asserting that attribute [{$name}] was set to the given value."
                : "Failed asserting that attribute [{$name}] was set.",
        );
    }

    public function assertNothingSet(): void
    {
        $this->assertNone('set', 'no attribute was set');
    }

    public function assertForgotten(Model $owner, string $name): void
    {
        Assert::assertTrue(
            $this->any(static fn (RecordedWrite $write): bool => $write->matches('forget', $owner, $name)),
            "Failed asserting that attribute [{$name}] was forgotten.",
        );
    }

    public function assertNothingForgotten(): void
    {
        $this->assertNone('forget', 'no attribute was forgotten');
    }

    /**
     * Assert the owner's attributes were synced — to exactly `$attributes`
     * (name => value, any order) when given.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function assertSynced(Model $owner, ?array $attributes = null): void
    {
        Assert::assertTrue(
            $this->any(static function (RecordedWrite $write) use ($owner, $attributes): bool {
                if (! $write->matches('sync', $owner)) {
                    return false;
                }

                if ($attributes === null) {
                    return true;
                }

                $expected = $attributes;
                $actual = $write->attributes;
                ksort($expected);
                ksort($actual);

                return $expected === $actual;
            }),
            $attributes === null
                ? 'Failed asserting that the owner\'s attributes were synced.'
                : 'Failed asserting that the owner\'s attributes were synced to the given set.',
        );
    }

    public function assertNothingSynced(): void
    {
        $this->assertNone('sync', 'no attributes were synced');
    }

    /**
     * Assert an attribute's meta was replaced — with exactly `$meta` when given.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function assertMetaSet(Model $owner, string $name, ?array $meta = null): void
    {
        Assert::assertTrue(
            $this->any(static fn (RecordedWrite $write): bool => $write->matches('meta', $owner, $name)
                && ($meta === null || $write->meta?->all() === $meta)),
            "Failed asserting that the meta of attribute [{$name}] was set".($meta === null ? '.' : ' to the given value.'),
        );
    }

    public function assertNothingMetaSet(): void
    {
        $this->assertNone('meta', 'no attribute meta was set');
    }

    public function assertPruned(): void
    {
        Assert::assertTrue($this->count('prune') > 0, 'Failed asserting that attributes were pruned.');
    }

    public function assertNothingPruned(): void
    {
        $this->assertNone('prune', 'no attributes were pruned');
    }

    private function assertNone(string $verb, string $what): void
    {
        $count = $this->count($verb);

        Assert::assertSame(0, $count, "Failed asserting that {$what}; recorded {$count}.");
    }

    private function count(string $verb): int
    {
        return count(array_filter($this->recorded, static fn (RecordedWrite $write): bool => $write->verb === $verb));
    }

    /**
     * @param  callable(RecordedWrite): bool  $predicate
     */
    private function any(callable $predicate): bool
    {
        foreach ($this->recorded as $write) {
            if ($predicate($write)) {
                return true;
            }
        }

        return false;
    }
}
