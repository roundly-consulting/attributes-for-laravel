<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;
use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\UniqueScope;
use RoundlyConsulting\Attributes\Events\AttributesSynced;
use RoundlyConsulting\Attributes\Exceptions\AttributesException;
use RoundlyConsulting\Attributes\Exceptions\DuplicateAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\InvalidAttributeValueException;
use RoundlyConsulting\Attributes\Exceptions\MissingRequiredAttributeException;
use RoundlyConsulting\Attributes\Exceptions\UnknownAttributeException;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Models\Attribute;
use RoundlyConsulting\Attributes\Tests\Models\LockingProduct;
use RoundlyConsulting\Attributes\Tests\Models\Product;
use RoundlyConsulting\Attributes\Tests\Models\SecondaryAttribute;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;

/**
 * A price range whose bounds refer to each other, and a shipping weight a physical
 * product needs: rules no single value can be validated against alone.
 */
beforeEach(function (): void {
    Attributes::flush()->defineMany(
        new AttributeDefinitionData('min_price', AttributeType::Integer, ['lte:max_price']),
        new AttributeDefinitionData('max_price', AttributeType::Integer, ['gte:min_price']),
        new AttributeDefinitionData('kind', AttributeType::String_, ['in:physical,digital']),
        new AttributeDefinitionData('weight', AttributeType::Float_, ['required_if:kind,physical']),
    );
});

/**
 * @return array<string, mixed>
 */
function attributesStored(Product $product): array
{
    return $product->getAttachedAttributes()->sortKeys()->all();
}

function attributesSetFailure(Closure $write): InvalidAttributeValueException
{
    try {
        $write();
    } catch (InvalidAttributeValueException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected the write to fail validation.');
}

it('fails a cross-attribute rule on the per-value default path', function (): void {
    $product = Product::query()->create();

    expect(fn () => $product->attachAttributes(['min_price' => 1, 'max_price' => 5]))
        ->toThrow(InvalidAttributeValueException::class);
});

it('validates and sets several values as one set', function (): void {
    $product = Product::query()->create();

    $written = Attributes::for($product)->setValidated(['min_price' => 1, 'max_price' => 5]);

    expect($written)->toHaveCount(2)
        ->and($written->every(fn (mixed $row): bool => $row instanceof Attribute))->toBeTrue()
        ->and(attributesStored($product))->toBe(['max_price' => 5, 'min_price' => 1]);
});

it('validates the written values against the stored ones', function (): void {
    $product = Product::query()->create();
    Attributes::for($product)->setValidated(['min_price' => 10, 'max_price' => 20]);

    // max_price carries the rule.
    $exception = attributesSetFailure(fn () => Attributes::for($product)->setValidated(['max_price' => 5]));

    // Both bounds now fail; the first in definition order names the exception.
    expect($exception->attributeName)->toBe('min_price')
        ->and($exception->errorBag()?->keys())->toBe(['min_price', 'max_price'])
        ->and(attributesStored($product))->toBe(['max_price' => 20, 'min_price' => 10]);
});

it('runs the rules of attributes the write does not touch', function (): void {
    Attributes::flush()->defineMany(
        new AttributeDefinitionData('min_price', AttributeType::Integer, ['lte:max_price']),
        new AttributeDefinitionData('max_price', AttributeType::Integer),
    );
    $product = Product::query()->create();
    Attributes::for($product)->setValidated(['min_price' => 10, 'max_price' => 20]);

    // Only min_price has a rule, and the write does not touch min_price.
    $exception = attributesSetFailure(fn () => Attributes::for($product)->setValidated(['max_price' => 5]));

    expect($exception->attributeName)->toBe('min_price')
        ->and($exception->getMessage())->toBe('Attribute [min_price] failed validation: The min_price field must be less than or equal to 5.')
        ->and($exception->messages())->toBe(['min_price: The min_price field must be less than or equal to 5.']);
});

it('fires implicit rules of a defined attribute that is not attached', function (): void {
    $product = Product::query()->create();

    $exception = attributesSetFailure(fn () => Attributes::for($product)->setValidated(['kind' => 'physical']));

    expect($exception->attributeName)->toBe('weight')
        ->and($exception->errorBag()?->first('weight'))->toBe('The weight field is required when kind is physical.')
        ->and(attributesStored($product))->toBe([]);

    Attributes::for($product)->setValidated(['kind' => 'physical', 'weight' => 1.5]);
    Attributes::for($product)->setValidated(['kind' => 'digital', 'weight' => null]);

    expect(attributesStored($product))->toBe(['kind' => 'digital', 'weight' => null]);
});

it('lets null pass every rule but the implicit ones', function (): void {
    $product = Product::query()->create();

    Attributes::for($product)->setValidated(['kind' => null]);

    expect(attributesStored($product))->toBe(['kind' => null]);
});

it('applies a rule referring to a missing attribute as a form request would', function (): void {
    // `gte:min_price` fails while min_price is missing — Laravel's own semantics; a
    // definition that should allow it says so (`exclude_without:min_price`).
    expect(fn () => Attributes::for(Product::query()->create())->setValidated(['max_price' => 3]))
        ->toThrow(InvalidAttributeValueException::class, 'max_price');

    Attributes::define(new AttributeDefinitionData('max_price', AttributeType::Integer, ['exclude_without:min_price', 'gte:min_price']));
    Attributes::define(new AttributeDefinitionData('min_price', AttributeType::Integer, ['exclude_without:max_price', 'lte:max_price']));
    $product = Product::query()->create();

    Attributes::for($product)->setValidated(['max_price' => 3]);

    expect(attributesStored($product))->toBe(['max_price' => 3]);
});

it('still enforces each value\'s type', function (): void {
    $exception = attributesSetFailure(fn () => Attributes::for(Product::query()->create())->setValidated(['min_price' => 'cheap']));

    expect($exception->errorBag()?->first('min_price'))->toBe('The min_price field must be an integer.');
});

it('writes nothing when one value of the set fails', function (): void {
    $product = Product::query()->create();
    Attributes::for($product)->setValidated(['min_price' => 1, 'max_price' => 2]);

    attributesSetFailure(fn () => Attributes::for($product)->setValidated(['kind' => 'vinyl', 'max_price' => 9]));

    expect(attributesStored($product))->toBe(['max_price' => 2, 'min_price' => 1]);
});

it('lets a rule refer to a free-form attribute', function (): void {
    Attributes::define(new AttributeDefinitionData('ends_at', AttributeType::DateTime, ['after:starts_at']));
    $product = Product::query()->create();
    $product->attachAttribute('starts_at', '2026-01-10');

    expect(fn () => Attributes::for($product)->setValidated(['ends_at' => '2026-01-01']))
        ->toThrow(InvalidAttributeValueException::class, 'ends_at');

    Attributes::for($product)->setValidated(['ends_at' => '2026-02-01']);

    expect($product->hasAttachedAttribute('ends_at'))->toBeTrue();
});

it('takes a dotted name literally', function (): void {
    Attributes::defineMany(
        new AttributeDefinitionData('size.width', AttributeType::Integer, ['min:1']),
        new AttributeDefinitionData('size.max', AttributeType::Integer, ['gte:size\.width']),
    );
    $product = Product::query()->create();

    Attributes::for($product)->setValidated(['size.width' => 4, 'size.max' => 6]);

    $exception = attributesSetFailure(fn () => Attributes::for($product)->setValidated(['size.max' => 2]));

    expect($exception->attributeName)->toBe('size.max')
        ->and($exception->errorBag()?->first('size.max'))->toContain('size.max');
});

it('validates encrypted stored values in their plain form', function (): void {
    Attributes::define(new AttributeDefinitionData('min_price', AttributeType::Integer, ['lte:max_price'], encrypted: true));
    $product = Product::query()->create();
    Attributes::for($product)->setValidated(['min_price' => 10, 'max_price' => 20]);

    expect(fn () => Attributes::for($product)->setValidated(['max_price' => 5]))
        ->toThrow(InvalidAttributeValueException::class, 'The min_price field must be less than or equal to 5.');
});

it('honours the owner\'s own definitions', function (): void {
    Attributes::flush();
    $product = new class extends Model implements HasAttributes
    {
        use RoundlyConsulting\Attributes\Traits\HasAttributes;

        protected $table = 'products';

        /** @var array<string, array<string, mixed>> */
        protected array $attributeDefinitions = [
            'from' => ['type' => 'integer', 'rules' => ['lt:to']],
            'to' => ['type' => 'integer'],
        ];
    };
    $product->save();

    expect(fn () => Attributes::for($product)->setValidated(['from' => 5, 'to' => 5]))
        ->toThrow(InvalidAttributeValueException::class, 'from');
});

it('skips validation when nothing is defined', function (): void {
    Attributes::flush();
    $product = Product::query()->create();

    Attributes::for($product)->setValidated(['anything' => 'goes']);

    expect(attributesStored($product))->toBe(['anything' => 'goes']);
});

it('rejects an unknown name in strict mode', function (): void {
    config()->set('attributes.strict', true);

    expect(fn () => Attributes::for(Product::query()->create())->setValidated(['nope' => 1]))
        ->toThrow(UnknownAttributeException::class);
});

it('enforces unique scopes', function (): void {
    Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, unique: UniqueScope::Global_));
    Attributes::for(Product::query()->create())->setValidated(['sku' => 'A-1']);

    expect(fn () => Attributes::for(Product::query()->create())->setValidated(['sku' => 'A-1']))
        ->toThrow(DuplicateAttributeValueException::class);
});

it('refuses an unsaved owner before validating', function (): void {
    expect(fn () => Attributes::for(new Product)->setValidated(['max_price' => 5]))
        ->toThrow(AttributesException::class, 'unsaved owner');
});

it('validates the complete set the host passes to validateSet()', function (): void {
    $product = Product::query()->create();

    Attributes::validateSet($product, ['min_price' => 1, 'max_price' => 2]);

    expect(fn () => Attributes::validateSet($product, ['min_price' => 3, 'max_price' => 2]))
        ->toThrow(InvalidAttributeValueException::class, 'min_price');
});

it('locks the owner row inside the write transaction', function (): void {
    $product = LockingProduct::query()->create();
    LockRecorder::flush();

    Attributes::for($product)->setValidated(['kind' => 'digital']);

    expect(LockRecorder::recorded())->toHaveCount(1)
        ->and(LockRecorder::recorded()[0]['marker'])->toBe('lock-for-update')
        ->and(LockRecorder::recorded()[0]['transactionDepth'])->toBeGreaterThanOrEqual(1);
});

it('takes no owner lock on the per-value path', function (): void {
    $product = LockingProduct::query()->create();
    LockRecorder::flush();

    Attributes::for($product)->set('kind', 'digital');

    expect(LockRecorder::recorded())->toBe([]);
});

it('holds the owner lock in a transaction on the owner\'s connection when the rows live elsewhere', function (): void {
    config()->set('database.connections.secondary', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('secondary');
    (require __DIR__.'/../../database/migrations/create_attributes_table.php')->up();
    DB::setDefaultConnection($default);
    config()->set('attributes.model', SecondaryAttribute::class);

    $product = Product::query()->create();

    $levels = [];
    SecondaryAttribute::creating(function () use (&$levels): void {
        $levels[] = [DB::connection()->transactionLevel(), DB::connection('secondary')->transactionLevel()];
    });

    Attributes::for($product)->setValidated(['min_price' => 1, 'max_price' => 2]);

    // The owner's connection holds its transaction (and the lock) for the whole write; the
    // rows' connection runs the write, each insert in a savepoint.
    expect($levels)->toBe([[1, 2], [1, 2]])
        ->and(SecondaryAttribute::query()->count())->toBe(2)
        ->and($product->attributeInt('max_price'))->toBe(2);
});

describe('with attributes.validation.whole_set on', function (): void {
    beforeEach(function (): void {
        config()->set('attributes.validation.whole_set', true);
    });

    it('validates set() against the whole set', function (): void {
        $product = Product::query()->create();
        $product->attachAttributes(['min_price' => 10, 'max_price' => 20]);

        expect(fn () => $product->attachAttribute('max_price', 5))->toThrow(InvalidAttributeValueException::class, 'less than or equal to 5');

        $product->attachAttribute('max_price', 15);

        expect($product->attributeInt('max_price'))->toBe(15);
    });

    it('validates setMany() and staged saves as one set', function (): void {
        $product = Product::query()->create();

        $product->attachAttributes(['min_price' => 1, 'max_price' => 5]);
        $product->attributes()->set('kind', 'physical')->set('weight', 2.0)->save();

        expect(fn () => $product->attributes()->set('weight', null)->save())
            ->toThrow(InvalidAttributeValueException::class, 'weight');

        expect(attributesStored($product))->toBe(['kind' => 'physical', 'max_price' => 5, 'min_price' => 1, 'weight' => 2.0]);
    });

    it('validates a sync as the new set alone, and rolls the detach back when it fails', function (): void {
        Event::fake([AttributesSynced::class]);
        $product = Product::query()->create();
        $product->attachAttributes(['kind' => 'physical', 'weight' => 2.0]);

        // The stored kind is detached by the sync, so weight is no longer required.
        $product->syncAttributes(['weight' => null, 'min_price' => 1, 'max_price' => 5]);

        expect(attributesStored($product))->toBe(['max_price' => 5, 'min_price' => 1, 'weight' => null]);
        Event::assertDispatched(AttributesSynced::class);

        expect(fn () => $product->syncAttributes(['kind' => 'physical']))
            ->toThrow(InvalidAttributeValueException::class, 'weight');

        expect(attributesStored($product))->toBe(['max_price' => 5, 'min_price' => 1, 'weight' => null]);
    });

    it('validates the stored set in validateAttributes()', function (): void {
        $product = Product::query()->create();
        $product->attachAttributes(['min_price' => 1, 'max_price' => 5]);

        $product->validateAttributes();

        // A stored set made inconsistent behind the package's back.
        Attribute::query()->where('name', 'max_price')->update(['value' => '0']);

        expect(fn () => $product->validateAttributes())->toThrow(InvalidAttributeValueException::class, 'less than or equal to 0');
    });

    it('still reports a missing required attribute first', function (): void {
        Attributes::define(new AttributeDefinitionData('sku', AttributeType::String_, required: true));

        expect(fn () => Product::query()->create()->validateAttributes())->toThrow(MissingRequiredAttributeException::class);
    });

    it('records each whole-set write through the fake', function (): void {
        $fake = Attributes::fake();
        $product = Product::query()->create();

        $product->attachAttributes(['min_price' => 1, 'max_price' => 5]);

        $fake->assertSet($product, 'min_price', 1);
        $fake->assertSet($product, 'max_price', 5);
    });
});

it('records setValidated() through the fake, once per attribute', function (): void {
    $fake = Attributes::fake();
    $product = Product::query()->create();

    Attributes::for($product)->setValidated(['min_price' => 1, 'max_price' => 5], ['min_price' => ['source' => 'import']]);

    $fake->assertSet($product, 'min_price', 1);
    $fake->assertSet($product, 'max_price', 5);

    expect($fake->recorded())->toHaveCount(2)
        ->and($fake->recorded()[0]->meta?->all())->toBe(['source' => 'import'])
        ->and(fn () => $fake->assertSet($product, 'kind'))->toThrow(AssertionFailedError::class);
});

it('records nothing through the fake when the set fails', function (): void {
    $fake = Attributes::fake();
    $product = Product::query()->create();

    attributesSetFailure(fn () => Attributes::for($product)->setValidated(['min_price' => 9, 'max_price' => 1]));

    $fake->assertNothingSet();
});
