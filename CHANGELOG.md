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

### Fixed

- `syncAttributes()` and `destroyAttributesExcept()` now remove attributes through the detach
  action, so they fire `AttributeDetached` and record a `detached` history revision (they used
  to delete rows silently).
