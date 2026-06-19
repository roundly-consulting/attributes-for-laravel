<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\Events\AttributeAttached;
use RoundlyConsulting\Attributes\Events\AttributeDetached;
use RoundlyConsulting\Attributes\Events\AttributesSynced;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('constructs the attached event with its payload', function (): void {
    $product = Product::create();
    $attribute = new Attribute(['name' => 'color']);

    $event = new AttributeAttached($product, $attribute);

    expect($event->owner)->toBe($product)
        ->and($event->attribute)->toBe($attribute);
});

it('constructs the detached event with its payload', function (): void {
    $product = Product::create();

    $event = new AttributeDetached($product, 'color');

    expect($event->owner)->toBe($product)
        ->and($event->name)->toBe('color');
});

it('constructs the synced event with its payload', function (): void {
    $product = Product::create();
    $data = [new AttributeData('color', 'white')];

    $event = new AttributesSynced($product, $data);

    expect($event->owner)->toBe($product)
        ->and($event->attributes)->toBe($data);
});
