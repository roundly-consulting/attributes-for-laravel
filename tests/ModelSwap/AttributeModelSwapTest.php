<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Fixtures\SwappedAttributeTestCase;
use RoundlyConsulting\Attributes\Tests\Models\CustomAttribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * The model-swap proof (S) for the `attributes.model` seam, driven through the REAL flows.
 *
 * This is the strong half of what `Unit/Support/AttributeModelTest.php` was reaching for.
 * That file stays (it still covers the resolver's fallbacks and its rejection of a
 * non-model), but its swap cases set the config at RUNTIME and asserted `instanceof`, and
 * both halves are too weak for the bugs this class of test exists for:
 *
 *  - a runtime `config()->set()` leaves every observer and every piece of boot-time wiring
 *    on the packaged Attribute (media #28);
 *  - `instanceof` passes for a row created as the packaged class — which never fires the
 *    host's model events (permissions #31). Only the concrete class, plus a `created`
 *    event counted on the subclass itself, proves the row was made as the host's model.
 *
 * The swap is applied before boot by {@see SwappedAttributeTestCase}, which this directory
 * is bound to — Pest binds a test case per directory, not per file.
 */
it('honours a host attribute model through every write flow', function (): void {
    expect('attributes.model')->toHonourModelSwap(CustomAttribute::class, function (): array {
        $product = Product::query()->create();

        // Attach, sync and re-read — the flows a host actually uses, through the trait's
        // public API rather than a resolver string check. The writers are fluent (they
        // return the owner), so the models under test come back off the relation.
        //
        // syncAttributes() runs after the attach and replaces the whole set, so `sku` is
        // deliberately part of the synced payload rather than attached and then dropped.
        $product->attachAttribute('sku', 'ABC-1');
        $product->syncAttributes(['sku' => 'ABC-2', 'colour' => 'red', 'size' => 'L']);

        return [
            $product->getAttachedAttribute('sku'),
            // The morph relation hydrates through the seam too, not just the writes.
            ...$product->attachedAttributes()->get()->all(),
        ];
    });
});

/**
 * The seam must survive the read side as well. A swap honoured on write but bypassed on
 * read would mean the typed accessors query a different model than the one the rows were
 * written as — invisible while both classes share a table, and fatal the moment the host's
 * subclass adds a global scope or a cast.
 */
it('reads typed values back through the swapped model', function (): void {
    $product = Product::query()->create();

    $product->attachAttribute('rating', 5);
    $product->attachAttribute('active', true);

    expect($product->attachedAttributes()->first())->toBeInstanceOf(CustomAttribute::class)
        ->and($product->attributeInt('rating'))->toBe(5)
        ->and($product->attributeBool('active'))->toBeTrue();
});

/**
 * The history trail is written by a separate action, so it needs its own proof: a swap
 * honoured by the attach flow but ignored by the revision recorder would silently write
 * the audit log through the packaged model.
 */
it('honours the swap while recording history', function (): void {
    config()->set('attributes.history.enabled', true);

    $product = Product::query()->create();
    $product->attachAttribute('sku', 'ABC-1');
    $product->attachAttribute('sku', 'ABC-2');

    expect($product->attachedAttributes()->first())->toBeInstanceOf(CustomAttribute::class)
        ->and($product->history())->not->toBeEmpty();
});

/**
 * The static `collect()` helper is the one call site that used to bypass the seam:
 * `static::query()` on the packaged class resolves to the packaged class, so a host that
 * swapped `attributes.model` and followed the README (`Attribute::collect()->keyByName()`)
 * silently got rows hydrated as the packaged Attribute — no casts, no global scopes, none
 * of the host's model events. Every other call site in the package already resolved through
 * `AttributeModel::class()`; this one did not.
 *
 * This is the permissions #34 class of bug, and the reason `modelsResolveThroughSeam` bans
 * late static binding outside the seam.
 */
it('resolves the configured model through the static collect helper', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('sku', 'ABC-1');

    expect(Attribute::collect())->each->toBeInstanceOf(CustomAttribute::class);
});

// The structural half of the seam — Attribute is non-final, and `attributes.model` really
// defaults to the packaged model — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.
