<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Attributes ships two CREATEs and zero foreign keys — both tables hang off an
 * unconstrained `nullableMorphs('owner')`, deliberately, because a host's attachable
 * entity can live in any table. Measured, not taken from the row spec.
 *
 * That shape decides what is worth pinning, and it is worth being explicit about why:
 *
 *  - **M (`toHaveRunnableMigrationOrder`) is not adopted.** With no FK edges there is no
 *    order to get wrong: the two migrations are independent CREATEs. `foreignKeys: 0`
 *    would pin a number that cannot change without a schema change.
 *  - **The R negative control (`toRejectBrokenOrderOnConnection`) is not adoptable.** It
 *    asserts the engine *refuses* a reordered set — with no foreign keys Postgres has
 *    nothing to refuse, so it would fail loudly by design ("the engine accepted the broken
 *    order"). That is the assertion working correctly against a shape it does not fit, not
 *    a red to chase and not a package defect.
 *
 * What remains is the half that bites: the DDL has to be something a real engine accepts.
 */
/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `count: 2` pins the file count so neither check can pass over an empty
 * or relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(AttributesServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes both migrations timestamp-injected into the host', function (): void {
    expect(AttributesServiceProvider::class)->toPublishMigrationsTimestamped('attributes-migrations', 2);
});

/**
 * R (`toApplyOnConnection`) is **deliberately not adopted yet** — HELD pending a fix in
 * testing-for-laravel, not because it does not fit.
 *
 * On the pgsql leg `DriverMatrix::configure()` builds `connections.testing` and
 * `connections.pgsql` from the same `connectionConfig('pgsql')` — identical host, port and
 * database. They are one physical database reached through two PDO sessions.
 * `MigrationRunner::runFiles()` drops every table on entry and again in `finally`, so
 * `toApplyOnConnection` pulls the schema out from under the *live suite* mid-run.
 *
 * This row saw it directly and misread it as leftover local state: the gate failed inside
 * the full suite while passing in isolation, which is the signature of the assertion
 * fighting the suite around it rather than of a bad migration.
 * `executionOrder="random"` makes it seed-dependent, so a green run currently proves
 * nothing.
 *
 * The rest of the real-engine value is kept: the whole suite still runs on Postgres on the
 * leg, and the driver-truth assertion below makes a lying leg impossible. Re-add R here
 * once the connection-isolation fix lands.
 */

/**
 * The columns the drivers genuinely render differently, round-tripped on whatever engine
 * the leg configured: `json` meta, the nullable `text` value, and the boolean
 * `is_encrypted` — sqlite stores booleans as integers and is happy to hand back `1` where
 * Postgres hands back a real bool, which is exactly the sort of thing a sqlite-only suite
 * cannot see.
 */
it('round-trips the attribute columns on the configured engine', function (): void {
    $product = Product::query()->create();

    $product->attachAttribute('rating', 5, collect(['source' => 'import', 'weight' => 2]));

    $attribute = $product->attachedAttributes()->first();

    expect($attribute->meta->all())->toBe(['source' => 'import', 'weight' => 2])
        ->and($attribute->is_encrypted)->toBeFalse()
        ->and($product->attributeInt('rating'))->toBe(5)
        // The driver actually under test, so a leg that quietly stayed on sqlite is
        // visible in the failure rather than passing as a "postgres" run. This fires
        // automatically; step 8's skip-count check is the human backstop.
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});
