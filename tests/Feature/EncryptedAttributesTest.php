<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\Product;

function defineEncrypted(string $name, AttributeType $type): void
{
    Attributes::define(new AttributeDefinitionData($name, $type, encrypted: true));
}

it('stores ciphertext at rest for each type', function (mixed $value, AttributeType $type): void {
    defineEncrypted('secret', $type);

    $product = Product::query()->create();
    $product->attachAttribute('secret', $value);

    $row = DB::table('attributes')->where('name', 'secret')->first();

    $read = $product->fresh()->getAttachedAttributeValue('secret');

    expect((int) $row->is_encrypted)->toBe(1)
        ->and($row->value)->not->toBe((string) (is_array($value) ? json_encode($value) : $value))
        // A datetime definition reads its value back as a date (the same instant).
        ->and($read instanceof DateTimeInterface ? $read->format(DATE_ATOM) : $read)->toEqual($value);
})->with([
    'string' => ['hunter2', AttributeType::String_],
    'integer' => [42, AttributeType::Integer],
    'float' => [4.25, AttributeType::Float_],
    'boolean' => [true, AttributeType::Boolean],
    'array' => [['k' => 'v'], AttributeType::Array_],
    'datetime' => ['2026-06-19T18:30:00+00:00', AttributeType::DateTime],
]);

it('skips crypt for a null encrypted value', function (): void {
    defineEncrypted('secret', AttributeType::String_);

    $product = Product::query()->create();
    $product->attachAttribute('secret', null);

    $row = DB::table('attributes')->where('name', 'secret')->first();

    expect($row->value)->toBeNull()
        ->and($product->fresh()->getAttachedAttributeValue('secret'))->toBeNull();
});

it('leaves undefined attributes as plaintext', function (): void {
    $product = Product::query()->create();
    $product->attachAttribute('plain', 'visible');

    $row = DB::table('attributes')->where('name', 'plain')->first();

    expect($row->value)->toBe('visible')
        ->and((int) $row->is_encrypted)->toBe(0);
});

it('decrypts correctly on a fresh instance', function (): void {
    defineEncrypted('secret', AttributeType::String_);

    $product = Product::query()->create();
    $product->attachAttribute('secret', 'top-secret');

    expect(Product::query()->find($product->id)?->getAttachedAttributeValue('secret'))->toBe('top-secret');
});

it('cannot match an encrypted value by an equality scope', function (): void {
    defineEncrypted('secret', AttributeType::String_);

    $product = Product::query()->create();
    $product->attachAttribute('secret', 'needle');

    // Ciphertext is non-deterministic, so a plaintext equality scope finds nothing.
    expect(Product::query()->whereAttribute('secret', 'needle')->count())->toBe(0);
});

it('round-trips a datetime through encryption', function (): void {
    defineEncrypted('expires', AttributeType::DateTime);

    $when = Carbon::parse('2026-12-31T23:59:00+00:00');

    $product = Product::query()->create();
    $product->attachAttribute('expires', $when);

    expect($product->fresh()->getAttachedAttributeValue('expires')->equalTo($when))->toBeTrue();
});
