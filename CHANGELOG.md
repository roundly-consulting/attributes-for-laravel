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
- Fluent builder (`$model->attributes()->set(...)->save()`), `syncAttributes()` and bulk reads
  via the `Attributes` facade.
- Query scopes to filter and sort owners by attribute: `whereAttribute()`, `whereAttributeIn()`,
  `whereAttributeBetween()`, `whereHasAttribute()`, `orderByAttribute()` and more.
- Attribute definitions with validation, defaults, `unique` (per owner type or global) and
  `required` constraints, and friendly validation errors.
- Per-model schemas declared on the model itself.
- Encrypted attribute values per definition.
- Optional old→new revision history (`attributes.history.enabled`), read with `history()`.
- `AttributeAttached`, `AttributeDetached` and `AttributesSynced` events.
- `attributes:list` and `attributes:prune` Artisan commands.
