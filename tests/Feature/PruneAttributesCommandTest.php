<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

function trashedAttribute(int $daysAgo, string $name): Attribute
{
    $product = Product::create();
    $product->attachAttribute($name, 'value');

    $attribute = $product->attachedAttributes()->where('name', $name)->firstOrFail();
    $attribute->delete();
    $attribute->forceFill(['deleted_at' => Carbon::now()->subDays($daysAgo)])->saveQuietly();

    return $attribute;
}

it('prunes only trashed attributes older than the cutoff', function (): void {
    trashedAttribute(60, 'old');
    trashedAttribute(5, 'recent');

    $this->artisan('attributes:prune', ['--days' => 30, '--force' => true])
        ->expectsOutputToContain('Pruned 1')
        ->assertSuccessful();

    $this->assertDatabaseMissing('attributes', ['name' => 'old']);
    $this->assertSoftDeleted('attributes', ['name' => 'recent']);
});

it('uses the configured default age when no days option is given', function (): void {
    config()->set('attributes.prune_after_days', 10);
    trashedAttribute(20, 'old');

    $this->artisan('attributes:prune', ['--force' => true])
        ->expectsOutputToContain('Pruned 1')
        ->assertSuccessful();

    $this->assertDatabaseMissing('attributes', ['name' => 'old']);
});

it('reports when there is nothing to prune', function (): void {
    $this->artisan('attributes:prune', ['--force' => true])
        ->expectsOutputToContain('No attributes to prune')
        ->assertSuccessful();
});

it('cancels when the confirmation is declined', function (): void {
    trashedAttribute(60, 'old');

    $this->artisan('attributes:prune', ['--days' => 30])
        ->expectsConfirmation('Permanently delete 1 trashed attribute(s)?', 'no')
        ->expectsOutputToContain('Pruning cancelled')
        ->assertSuccessful();

    $this->assertSoftDeleted('attributes', ['name' => 'old']);
});
