<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider attributes hard-requires, in registration order.
     *
     * The list is exactly one entry, and that is a measured fact rather than an
     * oversight: attributes `require`s enums-for-laravel and package-toolkit-for-laravel,
     * but neither ships a `laravel.providers` entry — the toolkit is the base class this
     * provider extends and enums is a trait/helper library. There is nothing for a host to
     * auto-discover, so nothing for the suite to mirror.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [AttributesServiceProvider::class];
    }

    /**
     * The attribute + revision migrations, named by provider class (never by filename —
     * the base case reflects each provider to its own `database/migrations`), plus the
     * host-owned `products` fixture table the attachable entity lives in.
     *
     * The fixture used to be a `Schema::create()` in `setUp()`. It is a migration now
     * because the real-engine reset drops every table and re-migrates between tests: a
     * table created in `setUp()` would survive the drop on the first test and be gone for
     * the second. On sqlite `:memory:` this is identical to what it replaced.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            AttributesServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * Attributes is one of the few packages that encrypts at rest — an `'encrypted' => true`
     * definition puts the value through Laravel's Crypt — so the suite needs a real
     * `app.key` or every encrypted read throws MissingAppKeyException.
     *
     * This is applied before boot, which is where it has to be: the encrypter is resolved
     * from the container and a key set afterwards would come too late.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ];
    }
}
