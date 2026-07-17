<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Tests\Models\Product;

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous — passing against empty output.
 *
 * Attributes is an unusually good fit for this assertion, because here the *key name is
 * itself the secret*. An attribute name is a host's field name — `api_token`, `ssn`,
 * `stripe_secret` — and the encrypted flag exists precisely because the value is
 * sensitive. The provider's own comment says definitions are reported "by count only" for
 * this reason; this is the test that holds it to that. Encrypting a value at rest and then
 * printing its name and ciphertext in `about` would be a leak the encryption was supposed
 * to prevent.
 */
it('renders the attributes section without leaking the names or values it protects', function (): void {
    config()->set('attributes.definitions', [
        'ssn' => ['type' => 'string', 'encrypted' => true],
        'stripe_secret' => ['type' => 'string', 'encrypted' => true],
        'rating' => ['type' => 'integer'],
    ]);

    $product = Product::query()->create();
    $product->attachAttribute('rating', 5);

    expect('attributes')->toLeakNoSecrets(
        secrets: [
            // The host's attribute names never render — not the encrypted ones, and not
            // the plain ones either. The count is the whole contract.
            'ssn',
            'stripe_secret',
            'rating',
            // Nor does any stored value, or the host entity it hangs off.
            Product::class,
        ],
        mustRender: [
            'Model',
            'Table',
            'Strict mode',
            'History',
            'Prune after',
            // The positive proof the definitions line reports rather than silently
            // rendering nothing: 3 defined, 2 of them encrypted.
            '3 defined (2 encrypted)',
        ],
    );
});

/**
 * The free-form branch of the same line. Without this, the assertion above could pass
 * against a section that only ever renders when definitions exist.
 */
it('reports free-form mode when no definitions are registered', function (): void {
    config()->set('attributes.definitions', []);

    expect('attributes')->toLeakNoSecrets(
        secrets: ['ssn'],
        mustRender: ['Definitions', 'FREE-FORM'],
    );
});
