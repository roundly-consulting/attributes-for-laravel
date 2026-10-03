<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/attributes-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=attributes-for-laravel">
    <img src="art/hero.png" alt="Attributes for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/attributes-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/attributes-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/attributes-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/attributes-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/attributes-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/attributes-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=attributes-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Attributes for Laravel

Attach multiple dynamic, **typed** key/value attributes to any Eloquent model. Add a single
trait to a model and store arbitrary, queryable attributes (with optional metadata) against it
through a polymorphic relationship — no schema changes per attribute, no EAV boilerplate.

Values keep their real PHP type (string, integer, float, boolean, array, datetime), an optional
registry validates known attributes, a fluent builder sets several at once, query scopes let you
filter owners by their attributes, and events let your app react to changes.

Beyond the typed core it also supports **encrypted values**, **unique** and **required**
constraints, **default values**, an optional **audit/history trail**, **per-model schemas**, a
typed `attr()` accessor, bulk read helpers, and friendly validation errors.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/attributes-for-laravel
```

Publish the migrations, then run them:

```bash
php artisan vendor:publish --tag="attributes-migrations"
php artisan migrate
```

The migrations are **publish-only** — the package never loads them for you. Publishing copies them
into your app's `database/migrations`, where you own them and can edit the schema before migrating.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="attributes-config"
```

## Configuration

The published config file (`config/attributes.php`) exposes the following keys:

```php
return [
    'model' => \RoundlyConsulting\Attributes\Models\Attribute::class,
    'table' => 'attributes',
    'key_type' => env('ATTRIBUTES_KEY_TYPE', 'bigint'),
    'strict' => env('ATTRIBUTES_STRICT', false),
    'prune_after_days' => env('ATTRIBUTES_PRUNE_AFTER_DAYS', 30),
    'history' => [
        'enabled' => env('ATTRIBUTES_HISTORY', false),
        'table' => 'attribute_revisions',
    ],
    'definitions' => [
        // 'rating'  => ['type' => 'integer', 'rules' => ['min:1', 'max:5'], 'required' => true],
        // 'sku'     => ['type' => 'string', 'unique' => 'global'],
        // 'token'   => ['type' => 'string', 'encrypted' => true],
        // 'retries' => ['type' => 'integer', 'default' => 3],
    ],
];
```

| Key | Type | Default | Env | Purpose |
|-----|------|---------|-----|---------|
| `model` | `class-string` | `RoundlyConsulting\Attributes\Models\Attribute` | — | Model used to persist attributes. Point it at a subclass to override casts/scopes. |
| `table` | `string` | `attributes` | — | Table name used by the migration and model. A blank or non-string value throws. |
| `key_type` | `string` | `bigint` | `ATTRIBUTES_KEY_TYPE` | Key type of the polymorphic `owner_id` column both migrations create: `bigint`, `uuid` or `ulid` (case-insensitive; anything else throws `InvalidConfigurationException` when the migrations run). Set it before you migrate; every owner model must share it. |
| `strict` | `bool` | `false` | `ATTRIBUTES_STRICT` | When `true`, writing an attribute that has neither a global definition nor one in the owner model's own schema throws `UnknownAttributeException`. |
| `prune_after_days` | `int` | `30` | `ATTRIBUTES_PRUNE_AFTER_DAYS` | Default age (days) for `attributes:prune`. A whole number, `0` or more (`0` purges every trashed attribute); anything else throws. |
| `history.enabled` | `bool` | `false` | `ATTRIBUTES_HISTORY` | When `true`, records an old→new revision on every attach/sync/detach/meta change. |
| `history.table` | `string` | `attribute_revisions` | — | Table name for the audit trail. A blank or non-string value throws. |
| `definitions` | `array` | `[]` | — | Registry seed. Each entry is an array supporting `type`, `rules`, `required`, `default`, `unique`, `encrypted`. |

The boolean env values accept `true`/`false`, `1`/`0`, `on`/`off` and `yes`/`no`. Every setting is
read strictly: an unset key takes its default, and a present but invalid value throws
`InvalidConfigurationException` naming the key — it never falls back silently.

Each definition entry accepts:

| Definition key | Type | Purpose |
|----------------|------|---------|
| `type` | `string` | One of `AttributeType`: `string`, `integer`, `float`, `boolean`, `array`, `datetime` (or the enum case); unset means `string`, anything else throws. Values are validated against it **and stored in it** — a request string `'5'` under an `integer` definition is stored and read back as `5`. |
| `rules` | `array` | Extra Laravel validation rules applied on attach. Must be an array (`['min:1', 'max:5']`); a pipe string throws. |
| `required` | `bool` | Enforced by `$model->validateAttributes()`. Read like the boolean env values; anything else throws. |
| `default` | `mixed` | Returned by reads when the attribute is not attached. |
| `unique` | `string`/`bool` | `'owner'` (per owner type), `'global'` (across every owner), `true` (= owner) or `false`/`'none'`; anything else throws instead of switching uniqueness off. Backed by a database unique index — encrypted values included. |
| `encrypted` | `bool` | Stores the value as ciphertext via `Crypt` at rest. Read like the boolean env values; anything else throws. |

## Usage

Add the `HasAttributes` trait and implement the matching contract on any model you want to attach
attributes to:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

class Product extends Model implements HasAttributesContract
{
    use HasAttributes;
}
```

### The `Attributes` facade

`Attributes::for($owner)` returns the owner's attribute handle — every read and write in one
place:

```php
use RoundlyConsulting\Attributes\Facades\Attributes;

// Writes (validated against definitions, recorded in history, events fired)
Attributes::for($product)->set('color', 'red', meta: ['hex' => '#f00']);     // Attribute
Attributes::for($product)->set('color', 'blue');                              // new value, meta kept
Attributes::for($product)->setMany(['color' => 'red', 'size' => 'L']);        // Collection<Attribute>, keeps others
Attributes::for($product)->sync(['color' => 'red'], forceDelete: false);      // exactly this set
Attributes::for($product)->forget(['color'], forceDelete: false);             // int removed ('color' works too)
Attributes::for($product)->forgetExcept(['color']);                           // list<string> removed
Attributes::for($product)->meta('color', ['hex' => '#ff0000']);               // replace one attribute's meta (null clears)
Attributes::for($product)->stage()->set('color', 'red')->meta('color', ['hex' => '#f00'])->save(); // or ->sync()

// Reads
Attributes::for($product)->all();          // Collection<name, value>
Attributes::for($product)->toKeyValue();   // array<name, value>
Attributes::for($product)->keys();         // list<string>
Attributes::for($product)->get('color');   // value (or the defined default)
Attributes::for($product)->has('color');   // bool
Attributes::for($product)->history('color'); // Collection<AttributeRevision>, newest first

// Housekeeping
Attributes::prune(30);   // force-delete attributes trashed more than 30 days ago (default: config)
```

`setMany()` and `sync()` take an optional per-name meta map as their last argument
(`meta: ['color' => ['hex' => '#f00']]`). A value write keeps the attribute's stored meta unless
you pass new meta; `meta()` replaces it (and goes through strict mode, history and the
`AttributeAttached` event like any other write). Definitions and validation are on the facade
too — see **Definitions & validation** below.

Writes are **all or nothing**: `setMany()`, `sync()` and `stage()->save()` validate every value
before the first write and run in one database transaction, so an invalid value (or a failing
write) leaves the owner exactly as it was — nothing detached, nothing half-written. Each owner
holds one row per attribute name (a unique index backs it); attaching a name that was detached
restores its row with a fresh value and meta.

| Method | Returns |
|---|---|
| `Attributes::for(Model $owner)` | `OwnerAttributes` — the handle above |
| `Attributes::prune(?int $days = null)` | `int` |
| `define()`, `defineMany()`, `forget(string $name)`, `flush()` | `AttributesManager` (chainable) |
| `has()`, `get()`, `all()`, `resolveFor()`, `definitionsFor()`, `default()`, `requiredNames()`, `isStrict()` | definition reads |
| `validate()`, `validateFor()`, `assertUnique()`, `assertKnown()` | `void` (throw on failure) |
| `Attributes::fake()` | `AttributesFake` — see **Testing helper** |

### Without the facade

The facade is sugar over `RoundlyConsulting\Attributes\AttributesManager`, a container
singleton. Inject it for the same API, or call an action for the raw use case — all three run
the same code:

```php
use RoundlyConsulting\Attributes\Actions\AttachAttributeAction;
use RoundlyConsulting\Attributes\AttributesManager;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;

final class ImportColor
{
    public function __construct(private AttributesManager $attributes) {}

    public function __invoke(Product $product, string $color): void
    {
        $this->attributes->for($product)->set('color', $color);
    }
}

// The raw action.
app(AttachAttributeAction::class)->execute($product, new AttributeData('color', 'red'));
```

| Action | Facade path |
|---|---|
| `AttachAttributeAction` | `for($o)->set()` |
| `AttachAttributesAction` | `for($o)->setMany()`, `for($o)->stage()->save()` |
| `SyncAttributesAction` | `for($o)->sync()`, `for($o)->stage()->sync()` |
| `DetachAttributesAction` | `for($o)->forget()` |
| `DetachAttributesExceptAction` | `for($o)->forgetExcept()` |
| `SyncAttributeMetaAction` | `for($o)->meta()` |
| `PruneAttributesAction` | `prune()` |

`RecordAttributeRevisionAction` is `@internal` — history is written by the actions above.

### Model methods

The `HasAttributes` trait keeps short model methods for the same operations. Every write goes
through `Attributes::for($this)`, so host overrides and `Attributes::fake()` see them:

| Model method | Same as |
|---|---|
| `attachAttribute($name, $value, $meta)` | `for($m)->set()` |
| `attachAttributes([...])` | `for($m)->setMany()` |
| `syncAttributes([...], $force)` | `for($m)->sync()` |
| `detachAttribute($name)`, `detachAttributes([...])`, `destroyAttributes([...])` | `for($m)->forget()` |
| `destroyAttributesExcept([...])` | `for($m)->forgetExcept()` |
| `syncAttributeMeta($name, $meta)` | `for($m)->meta()` |
| `attributes()` | `for($m)->stage()` |
| `history(?$name)` | `for($m)->history()` |

### Typed values

Values keep their real PHP type across write and read — no manual casting:

```php
$product->attachAttribute('color', 'white');     // string
$product->attachAttribute('rating', 5);          // int
$product->attachAttribute('on_sale', true);      // bool
$product->attachAttribute('price', 9.99);        // float
$product->attachAttribute('tags', ['a', 'b']);   // array
$product->attachAttribute('published_at', now()); // datetime

$product->getAttachedAttributeValue('rating');   // int(5)
$product->getAttachedAttributeValue('on_sale');  // true
```

The supported types are the cases of `RoundlyConsulting\Attributes\Enums\AttributeType`:
`string`, `integer`, `float`, `boolean`, `array`, `datetime`. Without a definition the type is
taken from the PHP value; with one, the definition's type wins. Datetimes are stored normalized
to UTC and read back as `CarbonImmutable` in your app timezone (the same instant); floats keep
their full precision.

### The fluent builder

Set several typed attributes, attach metadata, and persist them in one readable statement:

```php
$product->attributes()
    ->set('color', 'white')->meta('color', ['hex' => '#fff'])
    ->set('rating', 5)
    ->set('on_sale', true)
    ->save();
```

`->sync()` replaces all attributes with the staged set (removing any not staged). `->forget()`,
`->only()` and `->except()` adjust what is staged before saving.

### Reading attributes

```php
$product->getAttachedAttributes();                      // ['color' => 'white', 'rating' => 5]
$product->getAttachedAttributeValue('color');           // typed value (mixed)
$product->getAttachedAttributeValueAsString('rating');  // '5' (string view)
$product->getAttachedAttribute('color');                // Attribute model or null
$product->hasAttachedAttribute('color');                // bool
$product->getAttachedAttributeMeta('color');            // Collection|null

// Typed convenience readers:
$product->attributeInt('rating');      // ?int
$product->attributeFloat('price');     // ?float
$product->attributeBool('on_sale');    // ?bool
$product->attributeArray('tags');      // ?array
$product->attributeDate('published_at'); // ?Carbon
```

All read methods are eager-loading aware: `$product->load('attachedAttributes')` first and the
reads run against the loaded relation.

### The `attr()` accessor

`attr()` returns a typed value object — one entry point instead of the six `attributeX()`
methods (which still work). Defaults defined in a definition are applied automatically.

```php
$product->attr('rating')->int();        // ?int (falls back to the defined default)
$product->attr('price')->float();       // ?float
$product->attr('on_sale')->bool();      // ?bool
$product->attr('tags')->array();        // ?array
$product->attr('published_at')->date(); // ?Carbon
$product->attr('color')->string();      // ?string
$product->attr('color')->raw();         // underlying typed value
$product->attr('color')->isNull();      // bool
(string) $product->attr('color');       // '' when unset, else the value
```

### Bulk reads & exports

```php
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;

Attributes::for($product)->toKeyValue(); // array<name, value>

$product->attachedAttributes()->get()->toKeyValue(); // array<name, value>
Attribute::collect()->keyByName();                   // collection keyed by name
```

### Query scopes

Filter and order owner models by their attributes:

```php
Product::query()->whereAttribute('color', 'white')->get();
Product::query()->whereAttribute('rating', 5)->get();       // typed equality
Product::query()->whereAttributeIn('rating', [3, 5])->get();
Product::query()->whereHasAttribute('on_sale')->get();
Product::query()->whereDoesntHaveAttribute('on_sale')->get();
Product::query()->whereAttributeBetween('rating', 2, 4)->get();              // numeric range
Product::query()->whereAttributeBetween('published_at', $from, $to)->get();  // chronological
Product::query()->whereAttributeNull('note')->get();      // present, value NULL
Product::query()->whereAttributeNotNull('note')->get();
Product::query()->orderByAttribute('rating', 'desc')->get(); // 10, 9, 2 — numbers sort numerically
```

The `Attribute` model also exposes `forName`, `forOwner`, and `ofType` scopes.

> **Note:** comparisons are **typed**. The value you pass is taken in the attribute's defined
> type when it has a definition (so a request string `'5'` finds a stored integer `5`),
> otherwise in its own PHP type — an integer `5` does not match a stored string `'5'`. Integers
> and floats form one numeric family (`10` matches `10.0`). Numbers compare and sort
> numerically, datetimes chronologically (they are stored in UTC, so bounds in any timezone
> work), strings lexicographically. Encrypted values (below) cannot be matched, ranged or
> sorted by these scopes because their ciphertext is non-deterministic.

### Updating metadata and removing attributes

Removals soft-delete by default; pass `true` to force-delete permanently.

```php
Attributes::for($product)->meta('color', ['is_pretty' => 'yes']);
Attributes::for($product)->forget('color', forceDelete: true);

$product->syncAttributeMeta('color', collect(['is_pretty' => 'yes']));

$product->detachAttribute('color');
$product->detachAttribute('color', forceDelete: true);
$product->detachAttributes(['color', 'size']);
$product->destroyAttributesExcept(['color']);
$product->destroyAttributes(['price']);
```

Every removal — including the extras `syncAttributes()` / `destroyAttributesExcept()` drop —
fires `AttributeDetached` and records a `detached` revision when history is on. A force-deleting
`sync` / `forgetExcept` also purges previously soft-deleted extras.

### Syncing

`sync` makes the model's attributes match the given set exactly:

```php
Attributes::for($product)->sync(['color' => 'black', 'size' => 'large']);

$product->syncAttributes([
    'color' => 'black',
    'size'  => 'large',
]);
```

### Definitions & validation

Register known attributes with a type and validation rules. When `strict` is enabled, writing an
unregistered attribute (a value or its meta) throws `UnknownAttributeException` — a name the
owner model declares in its own schema counts as registered; registered attributes are always
validated and stored in their defined type.

```php
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;

Attributes::define(new AttributeDefinitionData(
    name: 'rating',
    type: AttributeType::Integer,
    rules: ['min:1', 'max:5'],
));

$product->attachAttribute('rating', 9); // throws InvalidAttributeValueException
```

You can also seed definitions through the `definitions` config key.

### Per-model schemas

A model can declare its own definitions, merged over the global config for that model only (strict
mode, validation, type, encryption, uniqueness and defaults all honour them). They are parsed
exactly like the config entries, so a non-array entry or a typo'd key throws naming
`App\Models\Product::attributeDefinitions.<name>.<key>`. Declare either a public
`attributeDefinitions()` method or a `$attributeDefinitions` property:

```php
class Product extends Model implements HasAttributesContract
{
    use HasAttributes;

    /** @var array<string, array<string, mixed>> */
    protected array $attributeDefinitions = [
        'rating'  => ['type' => 'integer', 'rules' => ['min:1', 'max:5'], 'required' => true],
        'sku'     => ['type' => 'string', 'unique' => 'global'],
        'token'   => ['type' => 'string', 'encrypted' => true],
        'retries' => ['type' => 'integer', 'default' => 3],
    ];
}
```

### Constraints — unique & required

```php
Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));

$a->attachAttribute('sku', 'ABC');
$b->attachAttribute('sku', 'ABC'); // throws DuplicateAttributeValueException

$product->validateAttributes(); // throws MissingRequiredAttributeException if a required key is absent
```

`unique` accepts `UniqueScope::Owner` (unique per owner type), `UniqueScope::Global_` (unique
across every owner), or `UniqueScope::None`. Re-saving the same owner's own value is idempotent,
and detaching an attribute frees its value.

Uniqueness is a **database guarantee**, not just a check before the write: each unique value
carries a deterministic hash in the `unique_hash` column, which has a unique index — two
concurrent writers of the same value cannot both commit (the loser gets
`DuplicateAttributeValueException`). It works for **encrypted** values too: their hash is a
keyed blind index (an HMAC under a key derived from `APP_KEY`), so the database never holds the
plaintext. Rotating `APP_KEY` changes that key — re-save encrypted unique values afterwards.

### Default values

A definition's `default` is returned by reads when the attribute is not attached (defaults apply
on **read only** — they are never persisted, bulk `getAttachedAttributes()` lists stored rows
only, and an attribute explicitly stored as `null` reads as `null`):

```php
Attributes::define(new AttributeDefinitionData('retries', AttributeType::Integer, default: 3));

$product->attributeInt('retries'); // 3 when unset, the stored value when set
$product->attr('retries')->int();  // 3
```

### Encrypted values

Mark a definition `encrypted: true` and its value is transparently `Crypt`-encrypted at rest and
decrypted on read. Works for every type; `null` values are never run through `Crypt`.

```php
Attributes::define(new AttributeDefinitionData('token', AttributeType::String_, encrypted: true));

$product->attachAttribute('token', 'secret');
$product->getAttachedAttributeValue('token'); // 'secret' (DB column holds ciphertext)
```

Because the ciphertext is non-deterministic, encrypted values cannot be matched, ranged or
ordered by the query scopes. `unique` still works for them (see **Constraints**).

### History / audit trail

Enable `attributes.history.enabled` (or `ATTRIBUTES_HISTORY=true`) to record an old→new revision
on every attach/sync/detach and meta change. Disabled by default, so there is no table cost unless
you opt in.

```php
Attributes::for($product)->history();        // Collection<AttributeRevision> — newest first
Attributes::for($product)->history('color'); // filtered by attribute name

$product->history();          // Collection<AttributeRevision> — newest first
$product->history('color');   // filtered by attribute name
$product->attributeHistory(); // the underlying MorphMany relation
```

Each revision stores the change `type` (`attached`/`updated`/`detached`), the old and new value,
their types, and meta. Encrypted attributes are stored in their ciphertext form, so the audit log
never leaks secrets. Pruning the revisions table is left to the host application.

### Friendly validation errors

`InvalidAttributeValueException` carries the failing attribute name and the full validator bag:

```php
catch (InvalidAttributeValueException $e) {
    $e->attributeName; // 'rating'
    $e->messages();    // ['rating: The rating field must not be greater than 5.']
    $e->errorBag();    // Illuminate\Support\MessageBag|null
}
```

### Events

Listen for these events to extend behaviour (audit logs, search re-indexing, syncing):

- `RoundlyConsulting\Attributes\Events\AttributeAttached` — `(Model $owner, Attribute $attribute)`, for
  every value or meta write
- `RoundlyConsulting\Attributes\Events\AttributeDetached` — `(Model $owner, string $name)`, only for
  attributes that were actually attached
- `RoundlyConsulting\Attributes\Events\AttributesSynced` — `(Model $owner, array $attributes)`

They are dispatched once the write's transaction commits (`ShouldDispatchAfterCommit`), so a
rolled-back write never announces itself.

### Console commands

```bash
# List the attributes attached to an owner record:
php artisan attributes:list "App\Models\Product" 42

# Permanently delete soft-deleted attributes older than N days (default from config):
php artisan attributes:prune --days=30 --force
```

The prune command asks before deleting and then runs `Attributes::prune($days)`, which you can
also schedule directly. `--days` must be a whole number (`0` or more); anything else fails the
command instead of being read as `0` and purging every trashed attribute.

### Testing helper

`Attributes::fake()` swaps the manager for a recording fake — in the facade **and** in the
container, so injected `AttributesManager`s, the owner handle, the staged writer and every
`HasAttributes` model method are recorded. Writes still hit your test database (so reads,
validation and history behave normally) and are recorded once they succeed. Definitions keep
using the real registry and are not recorded.

```php
use RoundlyConsulting\Attributes\Facades\Attributes;

$fake = Attributes::fake();

$product->attachAttribute('color', 'red');     // model method — recorded

$fake->assertSet($product, 'color', 'red');
$fake->assertNothingForgotten();
```

| Write | Assert | Negative |
|---|---|---|
| `set()`, `setMany()` (one per attribute), `stage()->save()`, `attachAttribute(s)()` | `assertSet($owner, $name, $value?)` — pass a value to compare it (`null` included) | `assertNothingSet()` |
| `forget()`, `forgetExcept()` (one per removed name), `detachAttribute(s)()`, `destroyAttributes*()` | `assertForgotten($owner, $name)` | `assertNothingForgotten()` |
| `sync()`, `stage()->sync()`, `syncAttributes()` | `assertSynced($owner, ?array $attributes)` | `assertNothingSynced()` |
| `meta()`, `syncAttributeMeta()` | `assertMetaSet($owner, $name, ?array $meta)` | `assertNothingMetaSet()` |
| `prune()`, `attributes:prune` | `assertPruned()` | `assertNothingPruned()` |
| anything | — | `assertNothingWritten()` |

`$fake->recorded()` returns the raw `RecordedWrite` list.

## Integrates with

This package builds on other roundly-consulting packages:

- **[enums-for-laravel](https://github.com/roundly-consulting/enums-for-laravel)** — a hard
  dependency. Every enum this package ships (`AttributeType`, `UniqueScope`, `RevisionType`)
  uses the `RoundlyConsulting\Enums\Helpers` trait, so each gains the full ergonomic surface on
  top of its existing domain methods: `values()`, `names()`, `labels()`, `options()`,
  `toOptions()`, `toArray()`, `collect()`, `count()`, `random()`, `readable()`/`label()`, the
  case lookups (`fromName()`, `tryFromName()`, `fromLabel()`, `tryFromLabel()`, `hasName()`,
  `hasValue()`), and the fluent comparators (`is()`, `isNot()`, `isIn()`, `isNotIn()`,
  `whenIs*()`).
- **[crypto-for-laravel](https://github.com/roundly-consulting/crypto-for-laravel)** — a hard
  dependency. Its deterministic digest and HMAC build the `unique_hash` behind `unique`
  definitions — the keyed blind index that makes uniqueness work for encrypted values.
- **[package-toolkit-for-laravel](https://github.com/roundly-consulting/package-toolkit-for-laravel)**
  — a hard dependency. It provides the service-provider builder (config, migrations, commands and
  publish tags) and the validated `attributes.model` resolver, which checks that a swapped-in model
  really is an attribute model before the package queries through it. The package also reports its
  configuration to Laravel's `about` command (`php artisan about --only=attributes`); attribute
  definitions are reported by count only — an attribute name is a host's field name and often names
  the very secret the `encrypted` flag protects.

```php
use RoundlyConsulting\Attributes\Enums\AttributeType;

// Value => label map for a select input.
AttributeType::toOptions(); // ['string' => 'String', 'integer' => 'Integer', ...]

// Case lookups.
AttributeType::fromName('Integer');   // AttributeType::Integer
AttributeType::hasValue('datetime');  // true
```

### `AttributeType::validationRule()` caveat

`AttributeType` keeps its own **instance** `validationRule()`, which returns the Laravel rule
for a value of that type (`'string'`, `'integer'`, `'numeric'`, `'boolean'`, `'array'`,
`'date'`). This intentionally shadows the trait's **static** `validationRule()` membership rule,
so `AttributeType` does not expose the `in:...` form under that name. If a host needs the
membership rule, build it from the values:

```php
'in:'.AttributeType::values()->implode(','); // in:string,integer,float,boolean,array,datetime
```

`UniqueScope` and `RevisionType` have no such method, so they expose the trait's static
`validationRule()` normally (`UniqueScope::validationRule()` → `in:none,owner,global`).

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=attributes-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=attributes-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE](LICENSE.md) for details.
