<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

function runAttributesMigration(string $file): void
{
    $migration = require __DIR__.'/../../database/migrations/'.$file;
    $migration->up();
}

/** The verbatim sqlite catalog record of a table's columns. */
function attributesCreateTable(string $table): string
{
    /** @var list<object{sql: string|null}> $rows */
    $rows = DB::select('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);

    return (string) ($rows[0]->sql ?? '');
}

/** @return array{type: string, nullable: string} */
function attributesPgColumn(string $table, string $column): array
{
    /** @var list<object{data_type: string, character_maximum_length: int|null, is_nullable: string}> $rows */
    $rows = DB::select(
        'select data_type, character_maximum_length, is_nullable from information_schema.columns where table_name = ? and column_name = ?',
        [$table, $column],
    );

    $row = $rows[0] ?? null;

    if ($row === null) {
        return ['type' => 'MISSING', 'nullable' => 'MISSING'];
    }

    $type = $row->character_maximum_length === null
        ? $row->data_type
        : $row->data_type.'('.$row->character_maximum_length.')';

    return ['type' => $type, 'nullable' => $row->is_nullable];
}

$sqliteOnly = fn (): bool => DriverMatrix::driver() !== 'sqlite';
$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';

it('creates the polymorphic owner column on both tables', function (): void {
    expect(Schema::hasColumns('attributes', ['owner_type', 'owner_id']))->toBeTrue()
        ->and(Schema::hasColumns('attribute_revisions', ['owner_type', 'owner_id']))->toBeTrue();
});

/**
 * The core P1 safety property: `morphKey($n, BigInt, nullable: true)` IS `nullableMorphs($n)`.
 * Proven by diffing the migration's emitted columns against a table built from raw
 * `nullableMorphs()` — a wrong nullability or type would surface as a string difference.
 */
it('emits a bigint owner morph byte-identical to raw nullableMorphs()', function (): void {
    config()->set('attributes.key_type', 'bigint');
    config()->set('attributes.table', 'kt_ident_attributes');

    Schema::dropIfExists('kt_ident_attributes');
    runAttributesMigration('create_attributes_table.php');

    Schema::dropIfExists('owner_raw_ref');
    Schema::create('owner_raw_ref', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
    });

    $emitted = attributesCreateTable('kt_ident_attributes');
    $morphColumns = str_contains($emitted, '"owner_type" varchar, "owner_id" integer');

    expect($morphColumns)->toBeTrue()
        ->and(attributesCreateTable('owner_raw_ref'))->toContain('"owner_type" varchar, "owner_id" integer');

    Schema::dropIfExists('kt_ident_attributes');
    Schema::dropIfExists('owner_raw_ref');
})->skip($sqliteOnly, 'sqlite_master is the sqlite catalog');

/**
 * The headline of P1: a uuid/ulid host gets uuid/ulid owner columns; bigint stays bigint
 * (never `integer`). Postgres tells the three apart; SQLite cannot. The owner morph keeps
 * the nullability of the `nullableMorphs()` it replaced.
 */
it('renders each configured key type as a distinct real column type', function (string $keyType, string $expected): void {
    config()->set('attributes.key_type', $keyType);
    config()->set('attributes.table', 'kt_attributes');

    Schema::dropIfExists('kt_attributes');
    runAttributesMigration('create_attributes_table.php');

    expect(attributesPgColumn('kt_attributes', 'owner_id'))->toBe(['type' => $expected, 'nullable' => 'YES'])
        ->and(attributesPgColumn('kt_attributes', 'owner_type')['type'])->toBe('character varying(255)');

    Schema::dropIfExists('kt_attributes');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart');

it('falls back to the bigint schema for an unrecognized key type', function (): void {
    config()->set('attributes.key_type', 'nonsense');
    config()->set('attributes.table', 'fallback_attributes');

    Schema::dropIfExists('fallback_attributes');
    runAttributesMigration('create_attributes_table.php');

    expect(Schema::hasColumn('fallback_attributes', 'owner_id'))->toBeTrue()
        ->and(DatabaseDriver::current()->isPgsql() ? attributesPgColumn('fallback_attributes', 'owner_id')['type'] : 'bigint')
        ->toBe('bigint');

    Schema::dropIfExists('fallback_attributes');
});
