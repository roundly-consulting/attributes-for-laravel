# Attributes for Laravel

Attach multiple dynamic key/value attributes to any Eloquent model. Add a single trait to a
model and store arbitrary, queryable attributes (with optional metadata) against it through a
polymorphic relationship — no schema changes per attribute, no EAV boilerplate.

## Requirements

- PHP 8.3+
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

The migration is auto-discovered, so the package works without publishing it. Publish only
when you want to customise the schema.

## Configuration

The published config file (`config/attributes.php`) exposes a single key:

```php
return [

    // The Eloquent model used to store attached attributes. Override this with your own
    // model (extending the package model) to customise stored-attribute behaviour.
    'model' => \RoundlyConsulting\Attributes\Models\Attribute::class,

];
```

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `model` | `class-string` | `RoundlyConsulting\Attributes\Models\Attribute` | The model used to persist attached attributes. Point it at a subclass to override casts, scopes, or table behaviour. |

## Usage

Add the `HasAttributes` trait to any model you want to attach attributes to:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

class Product extends Model
{
    use HasAttributes;
}
```

### Attaching attributes

```php
$product->attachAttribute('color', 'white');

// With metadata (stored as JSON):
$product->attachAttribute('color', 'white', collect(['is_unique' => 'yes']));

// Multiple at once:
$product->attachAttributes([
    'color' => 'black',
    'size'  => 'small',
]);
```

Attaching is idempotent — re-attaching the same name updates its value rather than creating a
duplicate.

### Reading attributes

```php
// All attributes as a name => value collection:
$product->getAttachedAttributes(); // ['color' => 'white']

// A single value:
$product->getAttachedAttributeValue('color'); // 'white'

// The underlying model (or null):
$product->getAttachedAttribute('color');

// Existence check:
$product->hasAttachedAttribute('color'); // true

// Metadata of an attribute:
$product->getAttachedAttributeMeta('color'); // Collection|null
```

All read methods are eager-loading aware: if you `$product->load('attachedAttributes')`
first, the reads run against the loaded relation instead of hitting the database again.

### Updating metadata

```php
$product->syncAttributeMeta('color', collect(['is_pretty' => 'yes']));
```

### Removing attributes

Removals soft-delete by default; pass `true` to force-delete permanently.

```php
// Remove one:
$product->detachAttribute('color');
$product->detachAttribute('color', forceDelete: true);

// Remove several:
$product->detachAttributes(['color', 'size']);

// Remove everything except the given names:
$product->destroyAttributesExcept(['color']);

// Remove only the given names:
$product->destroyAttributes(['price']);
```

### Syncing

`syncAttributes` makes the model's attributes match the given set exactly — attaching new
ones, updating existing values, and removing any not present.

```php
$product->syncAttributes([
    'color' => 'black',
    'size'  => 'large',
]);
```

### The relationship

`attachedAttributes()` is a standard `MorphMany` relationship, so you can eager-load,
constrain, and query it like any other:

```php
$product->load('attachedAttributes');

$product->attachedAttributes()->where('name', 'color')->exists();
```

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE](LICENSE.md) for details.
