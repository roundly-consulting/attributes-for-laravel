<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;

it('infers type from native php values', function (mixed $value, AttributeType $expected): void {
    expect(AttributeType::forValue($value))->toBe($expected);
})->with([
    'integer' => [42, AttributeType::Integer],
    'float' => [4.2, AttributeType::Float_],
    'boolean' => [true, AttributeType::Boolean],
    'array' => [['a' => 1], AttributeType::Array_],
    'datetime' => [new DateTimeImmutable('2026-06-19'), AttributeType::DateTime],
    'string' => ['hello', AttributeType::String_],
    'null' => [null, AttributeType::String_],
]);

it('round-trips each type through storage', function (AttributeType $type, mixed $value): void {
    $stored = $type->toStorage($value);

    expect($type->fromStorage($stored))->toEqual($value);
})->with([
    'string' => [AttributeType::String_, 'white'],
    'integer' => [AttributeType::Integer, 5],
    'float' => [AttributeType::Float_, 4.25],
    'boolean true' => [AttributeType::Boolean, true],
    'boolean false' => [AttributeType::Boolean, false],
    'array' => [AttributeType::Array_, ['hex' => '#fff', 'n' => 3]],
]);

it('round-trips datetimes through storage', function (): void {
    $now = Carbon::parse('2026-06-19 18:30:00');

    $stored = AttributeType::DateTime->toStorage($now);
    $restored = AttributeType::DateTime->fromStorage($stored);

    expect($restored->equalTo($now))->toBeTrue();
});

it('encodes booleans as 1 and 0', function (): void {
    expect(AttributeType::Boolean->toStorage(true))->toBe('1')
        ->and(AttributeType::Boolean->toStorage(false))->toBe('0');
});

it('stores datetimes as iso-8601', function (): void {
    $stored = AttributeType::DateTime->toStorage(Carbon::parse('2026-06-19 18:30:00', 'UTC'));

    expect($stored)->toBe('2026-06-19T18:30:00+00:00');
});

it('json-encodes arrays', function (): void {
    expect(AttributeType::Array_->toStorage(['a' => 1]))->toBe('{"a":1}');
});

it('returns null from storage when stored value is null', function (): void {
    expect(AttributeType::Integer->fromStorage(null))->toBeNull();
});

it('accepts numeric strings for integer storage', function (): void {
    expect(AttributeType::Integer->toStorage('7'))->toBe('7');
});

it('accepts numeric strings for float and date storage', function (): void {
    expect(AttributeType::Float_->toStorage('3.5'))->toBe('3.5')
        ->and(AttributeType::DateTime->toStorage('2026-06-19'))->toContain('2026-06-19');
});

it('throws when encoding an array as a string', function (): void {
    AttributeType::String_->toStorage(['a' => 1]);
})->throws(InvalidAttributeValueException::class);

it('throws when encoding a non-integer as integer', function (): void {
    AttributeType::Integer->toStorage('abc');
})->throws(InvalidAttributeValueException::class);

it('throws when encoding a non-numeric as float', function (): void {
    AttributeType::Float_->toStorage('abc');
})->throws(InvalidAttributeValueException::class);

it('throws when encoding a non-array as array', function (): void {
    AttributeType::Array_->toStorage('not-array');
})->throws(InvalidAttributeValueException::class);

it('throws when encoding a non-date as datetime', function (): void {
    AttributeType::DateTime->toStorage(123);
})->throws(InvalidAttributeValueException::class);

it('encodes a boolean and datetime passed to a string type', function (): void {
    expect(AttributeType::String_->toStorage(true))->toBe('1')
        ->and(AttributeType::String_->toStorage(Carbon::parse('2026-06-19', 'UTC')))->toContain('2026-06-19');
});

it('exposes a validation rule per type', function (AttributeType $type, string $rule): void {
    expect($type->validationRule())->toBe($rule);
})->with([
    [AttributeType::String_, 'string'],
    [AttributeType::Integer, 'integer'],
    [AttributeType::Float_, 'numeric'],
    [AttributeType::Boolean, 'boolean'],
    [AttributeType::Array_, 'array'],
    [AttributeType::DateTime, 'date'],
]);

it('decodes invalid json to an empty array', function (): void {
    expect(AttributeType::Array_->fromStorage('not json'))->toBe([]);
});

it('throws when an array cannot be json-encoded', function (): void {
    AttributeType::Array_->toStorage(["\xB1\x31"]);
})->throws(InvalidAttributeValueException::class);

it('encodes a date string to iso-8601', function (): void {
    $stored = AttributeType::DateTime->toStorage('2026-06-19');

    expect($stored)->toContain('2026-06-19')
        ->and(AttributeType::DateTime->fromStorage($stored)->toDateString())->toBe('2026-06-19');
});
