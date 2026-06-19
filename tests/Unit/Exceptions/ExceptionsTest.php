<?php

declare(strict_types=1);

use Illuminate\Support\MessageBag;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
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
