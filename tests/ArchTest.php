<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The presets replace attributes' four hand-written arch rules; the one bespoke rule with
 * no preset equivalent is kept at the bottom rather than dropped for tidiness.
 */
ArchPresets::strictTypes('RoundlyConsulting\Attributes');

/**
 * Two deliberate extension points are exempt: Attribute, which `attributes.model` invites
 * a host to subclass (pinned by the preset below instead — the two rules pull in opposite
 * directions on purpose), and AttributesException, the base every attributes error extends
 * so a host can catch them uniformly.
 *
 * The replaced rule scoped itself to Support/Models/Actions; this covers the whole
 * namespace, so Builders, Registry, Casts and the rest are now closed too — which is how
 * the un-final AttributesException surfaced at all.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Attributes', [Attribute::class, AttributesException::class]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable
 * model is a PHP fatal the moment a host uses the seam the config documents. The preset
 * also pins that `attributes.model` really defaults to the packaged model, so the seam
 * cannot rot in the other direction either — which the replaced `->ignoring(Attribute)`
 * could never see, since an exemption proves nothing about the config.
 *
 * AttributeRevision is deliberately NOT mapped: it is `final` and has no config key. The
 * revisions table is written by the package alone and is not a documented seam.
 */
ArchPresets::swappableModelsAreNotFinal([
    Attribute::class => 'attributes.model',
]);

/**
 * Attributes does no cryptography of its own — but it is one of the few packages that
 * *encrypts at rest* (`'encrypted' => true` definitions go through Laravel's Crypt).
 * That makes the ban load-bearing rather than theoretical: the temptation to hand-roll a
 * cipher or a hash here is real, and it belongs in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Attributes');

/**
 * `attributes.model` resolves through the AttributeModel seam in Support. Adopted rather
 * than rejected as jwt/toolkit rejected it: attributes has exactly the shape the preset
 * targets — a real Eloquent model behind a `*_model`-style key — so the stray-literal half
 * has something to say, and nothing here needs the late static binding the preset bans.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support');

/**
 * The Dependency Policy as a test. No `alsoAllow`: attributes' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red the graph is
 * wrong — never widen the allow-list to quiet it.
 */
/**
 * The morph-key seam, guarded. The owner columns migrated off raw `$table->morphs()` onto
 * `morphKey($name, KeyType::fromConfig(...))` so a uuid/ulid host can flip its whole graph
 * coherently — a hardcoded bigint id breaks those hosts on Postgres, and SQLite type
 * affinity hides it. This pin reds if a future migration reintroduces a raw morph.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();

/**
 * Kept: the presets have no equivalent, and the rule is real. AttributeType's backed
 * values are persisted in the `value_type` column and read back through
 * `AttributeType::tryFrom()`, so an int-backed or pure enum would silently change what is
 * written to every row.
 */
it('backs every enum with a string')
    ->expect('RoundlyConsulting\Attributes\Enums')
    ->toBeStringBackedEnums();
