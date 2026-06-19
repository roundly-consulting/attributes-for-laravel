<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\AttributesServiceProvider;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

beforeEach(function (): void {
    $this->registry = new AttributeRegistry;
});

it('defines and retrieves a definition', function (): void {
    $definition = new AttributeDefinitionData('rating', AttributeType::Integer);

    $this->registry->define($definition);

    expect($this->registry->has('rating'))->toBeTrue()
        ->and($this->registry->get('rating'))->toBe($definition)
        ->and($this->registry->get('missing'))->toBeNull();
});

it('defines many at once and lists them', function (): void {
    $this->registry->defineMany(
        new AttributeDefinitionData('color', AttributeType::String_),
        new AttributeDefinitionData('rating', AttributeType::Integer),
    );

    expect($this->registry->all())->toHaveCount(2);
});

it('forgets and flushes definitions', function (): void {
    $this->registry->define(new AttributeDefinitionData('color', AttributeType::String_));
    $this->registry->define(new AttributeDefinitionData('rating', AttributeType::Integer));

    $this->registry->forget('color');
    expect($this->registry->has('color'))->toBeFalse();

    $this->registry->flush();
    expect($this->registry->all())->toBe([]);
});

it('validates a value against its rules', function (): void {
    $this->registry->define(new AttributeDefinitionData(
        name: 'rating',
        type: AttributeType::Integer,
        rules: ['min:1', 'max:5'],
    ));

    $this->registry->validate('rating', 3);
})->throwsNoExceptions();

it('throws when a value fails its rules', function (): void {
    $this->registry->define(new AttributeDefinitionData(
        name: 'rating',
        type: AttributeType::Integer,
        rules: ['min:1', 'max:5'],
    ));

    $this->registry->validate('rating', 9);
})->throws(InvalidAttributeValueException::class);

it('derives a type rule from the definition', function (): void {
    $this->registry->define(new AttributeDefinitionData('rating', AttributeType::Integer));

    $this->registry->validate('rating', 'abc');
})->throws(InvalidAttributeValueException::class);

it('skips validation for undefined keys', function (): void {
    $this->registry->validate('whatever', 'anything');
})->throwsNoExceptions();

it('rejects unknown keys in strict mode', function (): void {
    config()->set('attributes.strict', true);

    $this->registry->assertKnown('unknown');
})->throws(UnknownAttributeException::class);

it('allows unknown keys when not strict', function (): void {
    config()->set('attributes.strict', false);

    $this->registry->assertKnown('unknown');
})->throwsNoExceptions();

it('reports strict mode from config', function (): void {
    config()->set('attributes.strict', true);
    expect($this->registry->isStrict())->toBeTrue();

    config()->set('attributes.strict', false);
    expect($this->registry->isStrict())->toBeFalse();
});

it('loads definitions from config via the provider', function (): void {
    config()->set('attributes.definitions', [
        'rating' => ['type' => 'integer', 'rules' => ['min:1', 'max:5'], 'required' => true, 'default' => 1],
    ]);

    // Re-boot the provider against the new config.
    app()->forgetInstance(AttributeRegistry::class);
    (new AttributesServiceProvider(app()))->boot();

    $registry = app(AttributeRegistry::class);

    expect($registry->has('rating'))->toBeTrue()
        ->and($registry->get('rating')?->type)->toBe(AttributeType::Integer)
        ->and($registry->get('rating')?->required)->toBeTrue();
});

it('skips malformed config entries and defaults bad types to string', function (): void {
    config()->set('attributes.definitions', [
        'good' => ['type' => 'unknown-type'],
        'bad' => 'not-an-array',
    ]);

    app()->forgetInstance(AttributeRegistry::class);
    (new AttributesServiceProvider(app()))->boot();

    $registry = app(AttributeRegistry::class);

    expect($registry->has('good'))->toBeTrue()
        ->and($registry->get('good')?->type)->toBe(AttributeType::String_)
        ->and($registry->has('bad'))->toBeFalse();
});
