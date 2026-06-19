<?php

declare(strict_types=1);

use Illuminate\Support\MessageBag;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\MissingRequiredAttributeException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;

it('builds an unknown attribute exception', function (): void {
    $exception = UnknownAttributeException::forName('color');

    expect($exception)
        ->toBeInstanceOf(AttributesException::class)
        ->getMessage()->toBe('Attribute [color] is not registered.');
});

it('builds an invalid value exception with a reason', function (): void {
    $exception = InvalidAttributeValueException::forName('rating', 'too big');

    expect($exception)
        ->toBeInstanceOf(AttributesException::class)
        ->getMessage()->toContain('rating')
        ->getMessage()->toContain('too big');
});

it('builds an invalid value exception from a validation message bag', function (): void {
    $bag = new MessageBag(['value' => ['The value must be an integer.']]);

    $exception = InvalidAttributeValueException::failedValidation('rating', $bag);

    expect($exception)
        ->toBeInstanceOf(AttributesException::class)
        ->getMessage()->toContain('rating')
        ->getMessage()->toContain('must be an integer');
});

it('carries the bag, name and key-named messages on a failed validation', function (): void {
    $bag = new MessageBag(['value' => [
        'The rating must be an integer.',
        'The rating must be between 1 and 5.',
    ]]);

    $exception = InvalidAttributeValueException::failedValidation('rating', $bag);

    expect($exception->attributeName)->toBe('rating')
        ->and($exception->errorBag())->toBe($bag)
        ->and($exception->messages())->toBe([
            'rating: The rating must be an integer.',
            'rating: The rating must be between 1 and 5.',
        ]);
});

it('has no bag but a set name on the forName path', function (): void {
    $exception = InvalidAttributeValueException::forName('rating', 'too big');

    expect($exception->attributeName)->toBe('rating')
        ->and($exception->errorBag())->toBeNull()
        ->and($exception->messages())->toBe([]);
});

it('builds a missing required attribute exception', function (): void {
    $exception = MissingRequiredAttributeException::forName('rating');

    expect($exception)
        ->toBeInstanceOf(AttributesException::class)
        ->and($exception->attributeName)->toBe('rating')
        ->and($exception->getMessage())->toContain('rating');
});

it('builds a duplicate value exception carrying the scope', function (): void {
    $exception = DuplicateAttributeValueException::forName(
        'sku',
        UniqueScope::Global_,
    );

    expect($exception)
        ->toBeInstanceOf(AttributesException::class)
        ->and($exception->attributeName)->toBe('sku')
        ->and($exception->scope)->toBe(UniqueScope::Global_)
        ->and($exception->getMessage())->toContain('sku')
        ->and($exception->getMessage())->toContain('global');
});
