<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\Testing\Concurrency\Racer;

/**
 * Two whole-set writes of one owner, for real: each validates the stored set and then
 * writes. Without the owner lock both would validate against the same committed set —
 * min_price = 10 against max_price = 20, max_price = 5 against min_price = 1 — and both
 * commit, leaving min_price = 10 > max_price = 5, a set neither write would have allowed.
 * With it, the second waits for the first and validates the set the first left.
 *
 * Needs a real engine and pcntl + posix (Racer).
 */
beforeEach(function (): void {
    Attributes::flush()->defineMany(
        new AttributeDefinitionData('min_price', AttributeType::Integer, ['lte:max_price']),
        new AttributeDefinitionData('max_price', AttributeType::Integer, ['gte:min_price']),
    );
});

it('serializes two whole-set writes of one owner', function (): void {
    $product = Product::query()->create();
    Attributes::for($product)->setValidated(['min_price' => 1, 'max_price' => 20]);

    DB::beginTransaction();
    Attributes::for($product)->setValidated(['min_price' => 10]);

    $racer = new Racer()
        ->run(function () use ($product): string {
            try {
                Attributes::for(Product::query()->findOrFail($product->getKey()))->setValidated(['max_price' => 5]);

                return 'written';
            } catch (Throwable $exception) {
                return $exception::class;
            }
        })
        ->waitUntilBlockedOrDone(10);

    DB::commit();

    expect($racer->outcome())->toBe(InvalidAttributeValueException::class)
        ->and($product->getAttachedAttributes()->sortKeys()->all())->toBe(['max_price' => 20, 'min_price' => 10]);
})->skip(fn (): bool => ! Racer::available(), 'needs a real engine and pcntl + posix');
