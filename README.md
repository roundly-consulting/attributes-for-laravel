# Attributes for Laravel

Attach multiple dynamic, **typed** key/value attributes to any Eloquent model. Add a single
trait to a model and store arbitrary, queryable attributes (with optional metadata) against it
through a polymorphic relationship — no schema changes per attribute, no EAV boilerplate.

Values keep their real PHP type (string, integer, float, boolean, array, datetime), an optional
registry validates known attributes, a fluent builder sets several at once, query scopes let you
filter owners by their attributes, and events let your app react to changes.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/attributes-for-laravel
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="attributes-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="attributes-config"
```

The migration is auto-discovered, so the package works without publishing it. Publish only when
you want to customise the schema.

## Configuration

The published config file (`config/attributes.php`) exposes the following keys:

```php
return [
    'model' => \RoundlyConsulting\Attributes\Models\Attribute::class,
    'table' => 'attributes',
    'strict' => env('ATTRIBUTES_STRICT', false),
    'prune_after_days' => env('ATTRIBUTES_PRUNE_AFTER_DAYS', 30),
    'definitions' => [
        // 'rating' => ['type' => 'integer', 'rules' => ['min:1', 'max:5']],
    ],
];
```

| Key | Type | Default | Env | Purpose |
|-----|------|---------|-----|---------|
| `model` | `class-string` | `RoundlyConsulting\Attributes\Models\Attribute` | — | Model used to persist attributes. Point it at a subclass to override casts/scopes. |
| `table` | `string` | `attributes` | — | Table name used by the migration and model. |
| `strict` | `bool` | `false` | `ATTRIBUTES_STRICT` | When `true`, attaching an unregistered attribute throws `UnknownAttributeException`. |
| `prune_after_days` | `int` | `30` | `ATTRIBUTES_PRUNE_AFTER_DAYS` | Default age (days) for `attributes:prune`. |
| `definitions` | `array` | `[]` | — | Registry seed: known attributes with `type`, `rules`, `required`, `default`. |

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
`string`, `integer`, `float`, `boolean`, `array`, `datetime`.

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

### Query scopes

Filter and order owner models by their attributes:

```php
Product::query()->whereAttribute('color', 'white')->get();
Product::query()->whereAttribute('rating', 5)->get();       // typed equality
Product::query()->whereAttributeIn('rating', [3, 5])->get();
Product::query()->whereHasAttribute('on_sale')->get();
Product::query()->whereDoesntHaveAttribute('on_sale')->get();
Product::query()->orderByAttribute('rating', 'desc')->get();
```

The `Attribute` model also exposes `forName`, `forOwner`, and `ofType` scopes.

### Updating metadata and removing attributes

Removals soft-delete by default; pass `true` to force-delete permanently.

```php
$product->syncAttributeMeta('color', collect(['is_pretty' => 'yes']));

$product->detachAttribute('color');
$product->detachAttribute('color', forceDelete: true);
$product->detachAttributes(['color', 'size']);
$product->destroyAttributesExcept(['color']);
$product->destroyAttributes(['price']);
```

### Syncing

`syncAttributes` makes the model's attributes match the given set exactly:

```php
$product->syncAttributes([
    'color' => 'black',
    'size'  => 'large',
]);
```

### Definitions & validation

Register known attributes with a type and validation rules. When `strict` is enabled, attaching
an unregistered attribute throws; registered attributes are always validated.

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

### Events

Listen for these events to extend behaviour (audit logs, search re-indexing, syncing):

- `RoundlyConsulting\Attributes\Events\AttributeAttached` — `(Model $owner, Attribute $attribute)`
- `RoundlyConsulting\Attributes\Events\AttributeDetached` — `(Model $owner, string $name)`
- `RoundlyConsulting\Attributes\Events\AttributesSynced` — `(Model $owner, array $attributes)`

### Console commands

```bash
# List the attributes attached to an owner record:
php artisan attributes:list "App\Models\Product" 42

# Permanently delete soft-deleted attributes older than N days (default from config):
php artisan attributes:prune --days=30 --force
```

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE](LICENSE.md) for details.
