# Changelog

All notable changes to `attributes-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- `HasAttributes` trait: attach any number of dynamic key/value attributes, with optional
  metadata, to any Eloquent model — no schema change per attribute.
- Values keep their real PHP type (string, integer, float, boolean, array, datetime), read back
  through typed helpers or the `attr()` accessor (`attr('rating')->int()`).
- The `Attributes` facade (root `AttributesManager`, injectable): `Attributes::for($owner)` returns
  a read **and** write handle — `set()`, `setMany()`, `sync()`, `forget()`, `forgetExcept()`,
  `meta()`, `stage()` (the fluent writer), `history()`, plus `all()` / `toKeyValue()` / `keys()` /
  `get()` / `has()` — and `Attributes::prune($days)`. The `HasAttributes` model methods
  (`attachAttribute()`, `syncAttributes()`, `$model->attributes()->set(...)->save()` …) delegate
  to it.
- `Attributes::fake()` — a recording fake that also captures injected-manager, staged-writer and
  model-method writes, with `assertSet()`, `assertForgotten()`, `assertSynced()`,
  `assertMetaSet()`, `assertPruned()` and an `assertNothing*` for each.
- Query scopes to filter and sort owners by attribute: `whereAttribute()`, `whereAttributeIn()`,
  `whereAttributeBetween()`, `whereHasAttribute()`, `orderByAttribute()` and more.
- Attribute definitions with validation, defaults, `unique` (per owner type or global) and
  `required` constraints, and friendly validation errors.
- Per-model schemas declared on the model itself.
- Encrypted attribute values per definition.
- Optional old→new revision history (`attributes.history.enabled`), read with `history()`.
- `AttributeAttached`, `AttributeDetached` and `AttributesSynced` events.
- `attributes:list` and `attributes:prune` Artisan commands.

### Changed

- The facade root is `AttributesManager` (was `AttributeRegistry`, which stays as the
  definitions registry the actions validate against). `Support\AttributeQuery` is replaced by
  `OwnerAttributes`; `AttributeRegistry::for()` is gone — use `Attributes::for()`.
- `SyncAttributesAction::execute()` returns the written `Attribute`s (`Collection<int, Attribute>`)
  instead of echoing its input.
- `RecordAttributeRevisionAction` is `@internal`.
- A definition's `type` is applied on store (`'5'` under an `integer` definition is stored as
  `5`); datetimes are stored normalized to UTC and read back in the app timezone.
- The query scopes compare typed values: `whereAttribute('code', 5)` no longer matches a stored
  string `'5'`, numbers range and sort numerically, datetimes chronologically.
- `unique` is backed by a unique index on a new `unique_hash` column (a keyed blind index for
  encrypted values, built with `crypto-for-laravel`, now a hard dependency); each owner holds one
  row per attribute name, and re-attaching a detached name restores its row.
- `AttributeAttached`, `AttributeDetached` and `AttributesSynced` dispatch after the write's
  transaction commits.
- `assertKnown()` takes an optional owner, so strict mode honours per-model schemas.

### Fixed

- `syncAttributes()` and `destroyAttributesExcept()` now remove attributes through the detach
  action, so they fire `AttributeDetached` and record a `detached` history revision (they used
  to delete rows silently).
- `attributeDate()` returned the current time for every stored datetime.
- `setMany()`, `sync()` and `stage()->save()` could leave a half-applied write when one value was
  invalid; every value is now validated first and the writes share one transaction.
- Strict mode rejected names declared in a model's own schema, and `meta()` bypassed strict
  mode, history and events.
- Setting a value without meta wiped the stored meta.
- `unique` was never enforced for encrypted values, and uniqueness / one-row-per-name were
  racy read-then-write checks.
- `AttributeDetached` fired for names that were never attached.
- Floats lost precision beyond 14 digits, and an explicitly stored `null` read as the default.
