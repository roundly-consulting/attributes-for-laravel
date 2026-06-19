<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('records nothing when history is disabled', function (): void {
    config()->set('attributes.history.enabled', false);

    $product = Product::query()->create();
    $product->attachAttribute('color', 'white');
    $product->syncAttributes(['color' => 'black']);
    $product->detachAttribute('color');

    expect(AttributeRevision::query()->count())->toBe(0)
        ->and($product->history())->toHaveCount(0);
});

it('records attached, updated and detached revisions', function (): void {
    config()->set('attributes.history.enabled', true);

    $product = Product::query()->create();
    $product->attachAttribute('color', 'white');
    $product->attachAttribute('color', 'black');
    $product->detachAttribute('color');

    $history = $product->history('color');

    expect($history)->toHaveCount(3)
        ->and($history[0]->type)->toBe(RevisionType::Detached)
        ->and($history[2]->type)->toBe(RevisionType::Attached)
        ->and($history[2]->old_value)->toBeNull()
        ->and($history[2]->new_value)->toBe('white')
        ->and($history[1]->type)->toBe(RevisionType::Updated)
        ->and($history[1]->old_value)->toBe('white')
        ->and($history[1]->new_value)->toBe('black')
        ->and($history[0]->old_value)->toBe('black')
        ->and($history[0]->new_value)->toBeNull();
});

it('filters history by name and returns all newest first', function (): void {
    config()->set('attributes.history.enabled', true);

    $product = Product::query()->create();
    $product->attachAttribute('color', 'white');
    $product->attachAttribute('size', 'large');

    expect($product->history('color'))->toHaveCount(1)
        ->and($product->history())->toHaveCount(2)
        ->and($product->history()->first()?->name)->toBe('size');
});

it('stores encrypted values as ciphertext in the audit log', function (): void {
    config()->set('attributes.history.enabled', true);
    Attributes::define(new AttributeDefinitionData('token', AttributeType::String_, encrypted: true));

    $product = Product::query()->create();
    $product->attachAttribute('token', 'plaintext-secret');

    $revision = $product->history('token')->first();

    expect($revision?->new_value)->not->toBe('plaintext-secret')
        ->and($revision?->new_value)->not->toBeNull();
});
