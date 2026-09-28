<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * Review 2026-09-28: "one row per owner + name" was only a read followed by a write, so
 * two concurrent set() calls could both insert. A unique index now backs it, and the
 * write path recovers when a concurrent writer wins the insert.
 */
it('rejects a second row for the same owner and name at the database level', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('color', 'red');

    $row = (array) DB::table('attributes')->first();
    unset($row['id']);

    expect(fn () => DB::table('attributes')->insert($row))->toThrow(UniqueConstraintViolationException::class);
});

it('updates the row a concurrent writer inserted first', function (): void {
    $product = Product::query()->create();

    // Another writer commits the same owner + name right after our read found nothing.
    $raced = false;
    DB::listen(function (QueryExecuted $query) use (&$raced, $product): void {
        if ($raced || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'attributes')) {
            return;
        }

        $raced = true;
        DB::table('attributes')->insert([
            'owner_type' => $product->getMorphClass(),
            'owner_id' => $product->getKey(),
            'name' => 'color',
            'value' => 'theirs',
            'value_type' => 'string',
            'is_encrypted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    Attributes::for($product)->set('color', 'ours');

    $rows = Attribute::query()->where('name', 'color')->get();

    expect($raced)->toBeTrue()
        ->and($rows)->toHaveCount(1)
        ->and($rows->first()?->value)->toBe('ours');
});

it('restores the soft-deleted row when a name is attached again', function (): void {
    config(['attributes.history.enabled' => true]);

    $product = Product::query()->create();
    Attributes::for($product)->set('color', 'red', meta: ['hex' => '#f00']);
    $product->detachAttribute('color');

    Attributes::for($product)->set('color', 'blue');

    $rows = Attribute::withTrashed()->where('name', 'color')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()?->trashed())->toBeFalse()
        ->and($rows->first()?->value)->toBe('blue')
        // A detached attribute is gone: re-attaching starts fresh rather than reviving its meta.
        ->and($rows->first()?->meta)->toBeNull()
        ->and($product->history('color')->first()?->type)->toBe(RevisionType::Attached);
});
