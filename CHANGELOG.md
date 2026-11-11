# Changelog

All notable changes to `attributes-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## 2.2.0 - 2026-11-11

### Added

- `'unique' => 'scope'` (`UniqueScope::Scope`): a value is unique per owner type **within a scope
  model** the owner resolves — a team, a tenant — instead of across every owner of the type. Name
  the scope with the definition's new `unique_scope` key (an attribute or relation name on the
  owner such as `team_id` / `team`, or the class-string of an invokable implementing the new
  `Contracts\ResolvesUniqueScope`), with a closure in `AttributeDefinitionData`'s new
  `uniqueScope` argument, or for every scoped definition at once with the new
  `attributes.unique_scope` setting. Backed by the existing `unique_hash` unique index, so
  concurrent writers are still settled by the database — no migration needed. An owner that
  resolves no scope is refused (`AttributesException`), never checked against a shared pool. A
  `unique_scope` on a definition that is not `'scope'` throws instead of being ignored.

### Planned for the next major

- `'unique' => 'owner'` is renamed `'owner_type'`: it has always meant one pool per owner type,
  which in a multi-tenant app spans every tenant. Use `'scope'` for per-tenant uniqueness.

## 2.1.0 - 2026-11-09

### Added

- Revisions can record who made each change: turn on `attributes.history.actor.enabled`
  (`ATTRIBUTES_HISTORY_ACTOR`) after publishing and running the new additive
  `update_attribute_revisions_add_actor` migration (a nullable `actor` morph; key type
  `attributes.actor_key_type`). The actor is the one a write names with
  `Attributes::for($model)->by($user)`, else what `Attributes::resolveActorUsing()` returns, else
  the authenticated user of `attributes.history.actor.guard`. `AttributeRevision` gains `actor()`,
  `actorType()` and `actorId()`.
- `AttributeRevisionResource` renders a revision for an API; its actor renders through the
  swappable `attributes.resources.revision_actor` resource (default `{id, type}` with the class
  basename — no class names over the wire).
- Whole-set validation, so rules may refer to other attributes (`gte:min_price`,
  `required_if:kind,physical`): `Attributes::for($model)->setValidated([...])` validates the stored
  values overlaid by the new ones in one pass and writes all or nothing, with the owner's row locked
  so concurrent writes of one owner validate one after another. `Attributes::validateSet()` validates
  a complete set without writing. `attributes.validation.whole_set` (`ATTRIBUTES_VALIDATE_WHOLE_SET`,
  off by default) routes `set()`, `setMany()`, staged saves, `sync()` and `validateAttributes()`
  through it.
- `about` shows the history-actor and whole-set-validation settings.

### Changed

- Requires `illuminate/http` (for the new resources).

### Planned for the next major

- `attributes.history.actor.enabled` defaults to on (history itself stays opt-in). Before
  upgrading, publish and run the `update_attribute_revisions_add_actor` migration.

## 2.0.0 - 2026-11-07

### Changed

- **Breaking:** requires Laravel 13; Laravel 12 is no longer supported. Upgrade the application to Laravel 13 before updating.
- Requires the 2.x releases of the Roundly packages it builds on (their Laravel 13 releases).
- Maintenance: tested on PHP 8.4, 8.5 and 8.6; the test suite runs on Pest 5.

## 1.1.2 - 2026-10-17

### Fixed

- Factories build the configured model when a host swaps it.

## 1.1.1 - 2026-10-11

### Changed

- Maintenance: requires the latest roundly packages — package-toolkit `^1.3.0`, enums `^1.1.0`,
  crypto `^1.1.2`; dev: testing `^1.2.1`.

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
