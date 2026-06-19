<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('builds a revision via its factory', function (): void {
    $revision = AttributeRevision::factory()->create([
        'owner_type' => 'product',
        'owner_id' => 1,
    ]);

    expect($revision->fresh())
        ->toBeInstanceOf(AttributeRevision::class)
        ->and($revision->fresh()->type)->toBe(RevisionType::Attached);
});

it('casts the type and meta columns', function (): void {
    $revision = AttributeRevision::factory()->ofType(RevisionType::Updated)->create([
        'owner_type' => 'product',
        'owner_id' => 1,
        'old_meta' => ['source' => 'a'],
        'new_meta' => ['source' => 'b'],
    ]);

    expect($revision->fresh()->type)->toBe(RevisionType::Updated)
        ->and($revision->fresh()->old_meta?->get('source'))->toBe('a')
        ->and($revision->fresh()->new_meta?->get('source'))->toBe('b');
});

it('resolves its table from config', function (): void {
    config()->set('attributes.history.table', 'attribute_revisions');

    expect((new AttributeRevision)->getTable())->toBe('attribute_revisions');
});

it('exposes a polymorphic owner relation', function (): void {
    $product = Product::query()->create();

    $revision = AttributeRevision::factory()->create([
        'owner_type' => $product->getMorphClass(),
        'owner_id' => $product->getKey(),
    ]);

    expect($revision->owner)->not->toBeNull()
        ->and($revision->owner?->is($product))->toBeTrue();
});
