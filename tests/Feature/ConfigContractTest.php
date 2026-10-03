<?php

declare(strict_types=1);

/**
 * The config contract attributes never had, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`;
 *    330 tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead config
 *    that lies to the host: media #27's `max_file_size` cap that never applied, alerts
 *    #24's thrice-documented `escalation` key. Attributes ships a 60-line comment block
 *    documenting the `definitions` sub-keys, which is exactly the kind of prose that keeps
 *    a dead key looking alive.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/attributes.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but AttributesServiceProvider::contributesToAbout() calls
        // config('attributes.strict'), config('attributes.history.enabled'),
        // config('attributes.table'), config('attributes.prune_after_days') and
        // config('attributes.definitions') for real, inside the closure it renders from.
        // Excluding it would discard readers and weaken the reverse direction for nothing.
    ]);
});
