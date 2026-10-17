<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\CustomAttribute;

/**
 * The shipped factory builds the model the host configured. Without a `modelName()` override
 * it built its packaged `$model`, so a host seeding through `Attribute::factory()` got packaged
 * rows — without its own casts, events or observers. Exact classes, not `instanceof`: the
 * custom model extends the packaged one, so `toBeInstanceOf` passes either way.
 */
it('builds the configured attribute model', function (): void {
    $owner = ['owner_type' => 'product', 'owner_id' => 1];

    expect(Attribute::factory()->create($owner)::class)->toBe(CustomAttribute::class)
        ->and(CustomAttribute::factory()->create($owner)::class)->toBe(CustomAttribute::class);
});

it('fires the host model events for a factory-built attribute', function (): void {
    CustomAttribute::resetCreationCount();

    Attribute::factory()->count(2)->create(['owner_type' => 'product', 'owner_id' => 1]);

    expect(CustomAttribute::creationCount())->toBe(2);
});
