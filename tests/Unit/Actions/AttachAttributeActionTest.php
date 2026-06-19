<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Attributes\Actions\AttachAttributeAction;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Events\AttributeAttached;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;
use RoundlyConsulting\Attributes\Tests\Models\Product;

it('attaches a typed attribute and dispatches an event', function (): void {
    Event::fake();
    $product = Product::create();

    $attribute = app(AttachAttributeAction::class)->execute($product, new AttributeData('rating', 5));

    expect($attribute)->toBeInstanceOf(Attribute::class)
        ->and($attribute->value)->toBe(5)
        ->and($attribute->value_type)->toBe(AttributeType::Integer->value);

    Event::assertDispatched(AttributeAttached::class, fn (AttributeAttached $event): bool => $event->owner->is($product) && $event->attribute->name === 'rating');
});

it('updates an existing attribute on the same name', function (): void {
    $product = Product::create();
    $action = app(AttachAttributeAction::class);

    $action->execute($product, new AttributeData('color', 'white'));
    $action->execute($product, new AttributeData('color', 'black'));

    expect($product->attachedAttributes()->where('name', 'color')->count())->toBe(1)
        ->and($product->getAttachedAttributeValue('color'))->toBe('black');
});

it('validates against a registered definition', function (): void {
    app(AttributeRegistry::class)->define(new AttributeDefinitionData('rating', AttributeType::Integer, ['max:5']));
    $product = Product::create();

    app(AttachAttributeAction::class)->execute($product, new AttributeData('rating', 9));
})->throws(InvalidAttributeValueException::class);

it('rejects an unknown key in strict mode', function (): void {
    config()->set('attributes.strict', true);
    $product = Product::create();

    app(AttachAttributeAction::class)->execute($product, new AttributeData('surprise', 'value'));
})->throws(UnknownAttributeException::class);
