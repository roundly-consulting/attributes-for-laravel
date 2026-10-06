# Changelog

All notable changes to `attributes-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.1.0 - 2026-10-06

### Added

- Permanently deleting an owner (a model without `SoftDeletes`, or `forceDelete()`) removes its
  attribute rows and frees its unique values; soft-deleting it keeps them. Recorded revisions
  are kept. On by default — turn it off with `attributes.delete_with_owner`
  (`ATTRIBUTES_DELETE_WITH_OWNER`).

### Changed

- `bool()` and `attributeBool()` parse a string the way the write path does: `'false'`, `'off'`,
  `'no'`, `'0'` and `''` read as `false` (they read as `true` before).
- Under `Attributes::fake()`, `forget()` records only the names it actually removed, as
  `forgetExcept()` already did — forgetting a name that was never attached records nothing.
- Arrays keep whole-number floats (`[2.0]` is stored and read back as `[2.0]`, not `[2]`).
  Equality and unique checks compare the stored JSON, so an array written before this release
  as `[2]` is matched by `[2]`, not by `[2.0]`.
- Writing to an owner that is not saved (or was deleted) throws `AttributesException` instead
  of storing a row with no owner key.
- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.
- Maintenance: CI also runs the test suite against MySQL 8, alongside SQLite and PostgreSQL.
- Documentation: the README hero image uses an absolute URL, so it renders on Packagist and other
  sites.

### Fixed

- Two concurrent first writes on MySQL — of one owner, or of owners whose keys sit next to each
  other — deadlocked (`1213`) instead of one updating the other's row.
- A write did not refresh an eager-loaded `attachedAttributes` relation, so later reads (and
  `validateAttributes()`) on the same model saw the old values.
- A blank string under a `datetime` definition stored the current time; it is refused now.
- An attribute created through the `attachedAttributes()` relation or the factory — or with
  `value` listed before `name` — ignored its definition: encrypted values were stored in
  plaintext, types and owner-scoped unique hashes were wrong.
- `whereAttributeBetween()` with string bounds matched encrypted values and values of other
  types.
- `attr()->date()` and `attributeDate()` returned the current time for a blank or boolean value
  and threw for other non-dates; they return `null`.
- The factory's `encrypted()` state stored plaintext under the encrypted flag, and `ofType()`
  ignored the type it was given.
- The `Attribute` model documented a `$type` property that threw; use `type()`.
- An `integer` definition refused `'+5'`, `' 5'` and `'-0'` after validation had accepted them.
- `attributes:list` with an owner class name listed nothing when a morph map was in use.
- `$attribute->restore()` brought a unique value back without its unique hash; it is protected
  again, and restoring a value another owner holds by now throws
  `DuplicateAttributeValueException`.

## 1.0.0 - 2026-10-03

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
