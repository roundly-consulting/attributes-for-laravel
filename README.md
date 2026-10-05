<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/attributes-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=attributes-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/attributes-for-laravel/main/art/hero.png" alt="Attributes for Laravel — Roundly open source" width="100%">
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

Attach dynamic, typed key/value attributes to any Eloquent model through one polymorphic table:
no schema change per attribute, no EAV boilerplate. Values keep their real PHP type, an optional
registry validates them, and query scopes filter owners by their attributes.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/attributes-for-laravel
php artisan vendor:publish --tag="attributes-migrations"
php artisan migrate
```

If your owner models have UUID/ULID keys, set `ATTRIBUTES_KEY_TYPE` **before** migrating.

## Usage

Add the trait and its contract to any model:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes as HasAttributesContract;
use RoundlyConsulting\Attributes\Traits\HasAttributes;

class Product extends Model implements HasAttributesContract
{
    use HasAttributes;
}
```

Then write, read and query typed attributes (definitions are optional):

```php
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Facades\Attributes;

Attributes::define(new AttributeDefinitionData(
    name: 'rating',
    type: AttributeType::Integer,
    rules: ['min:1', 'max:5'],
));

Attributes::for($product)->set('color', 'red', meta: ['hex' => '#f00']);
Attributes::for($product)->setMany(['rating' => '5', 'on_sale' => true]);   // '5' is stored as 5

Attributes::for($product)->get('rating');     // 5
Attributes::for($product)->toKeyValue();      // ['color' => 'red', 'rating' => 5, 'on_sale' => true]
Attributes::for($product)->set('rating', 9);  // throws InvalidAttributeValueException

Product::query()->whereAttribute('color', 'red')->whereAttributeBetween('rating', 4, 5)->get();
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/attributes-for-laravel](https://roundly-consulting.com/open-source/docs/attributes-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=attributes-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

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
