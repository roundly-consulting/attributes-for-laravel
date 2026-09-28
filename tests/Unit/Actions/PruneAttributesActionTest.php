<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Actions\PruneAttributesAction;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('force-deletes trashed attributes older than the cutoff', function (): void {
    $product = Product::create();
    $product->attachAttributes(['old' => 1, 'recent' => 2, 'live' => 3]);
    $product->detachAttributes(['old', 'recent']);

    Attribute::withTrashed()->where('name', 'old')->update(['deleted_at' => Carbon::now()->subDays(45)]);

    expect(app(PruneAttributesAction::class)->execute(30))->toBe(1);

    $this->assertDatabaseMissing('attributes', ['name' => 'old']);
    $this->assertSoftDeleted('attributes', ['name' => 'recent']);
    $this->assertDatabaseHas('attributes', ['name' => 'live', 'deleted_at' => null]);
});

it('falls back to attributes.prune_after_days', function (): void {
    config()->set('attributes.prune_after_days', 10);

    $product = Product::create();
    $product->attachAttribute('old', 1);
    $product->detachAttribute('old');

    Attribute::withTrashed()->where('name', 'old')->update(['deleted_at' => Carbon::now()->subDays(11)]);

    expect(app(PruneAttributesAction::class)->execute())->toBe(1);
});
