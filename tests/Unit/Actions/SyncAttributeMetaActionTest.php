<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Actions\SyncAttributeMetaAction;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('updates the meta of an existing attribute', function (): void {
    $product = Product::create();
    $product->attachAttribute('color', 'white');

    $attribute = app(SyncAttributeMetaAction::class)->execute($product, 'color', collect(['hex' => '#fff']));

    expect($attribute->meta?->get('hex'))->toBe('#fff')
        ->and($product->getAttachedAttributeValue('color'))->toBe('white');
});

it('creates the attribute when it does not exist yet', function (): void {
    $product = Product::create();

    app(SyncAttributeMetaAction::class)->execute($product, 'color', collect(['hex' => '#000']));

    expect($product->getAttachedAttributeMeta('color')?->get('hex'))->toBe('#000');
});
