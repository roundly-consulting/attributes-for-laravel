<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Actions\RecordAttributeRevisionAction;
use RoundlyConsulting\Attributes\DataTransferObjects\RevisionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\RevisionType;
use RoundlyConsulting\Attributes\Models\AttributeRevision;
use RoundlyConsulting\Attributes\Tests\Models\Product;

beforeEach(function (): void {
    $this->action = app(RecordAttributeRevisionAction::class);
    $this->product = Product::query()->create();
});

it('is a no-op when history is disabled', function (): void {
    config()->set('attributes.history.enabled', false);

    $result = $this->action->execute($this->product, new RevisionData(
        name: 'color',
        type: RevisionType::Attached,
        oldValue: null,
        newValue: 'white',
        oldValueType: null,
        newValueType: AttributeType::String_,
    ));

    expect($result)->toBeNull()
        ->and($this->action->enabled())->toBeFalse()
        ->and(AttributeRevision::query()->count())->toBe(0);
});

it('writes a revision when history is enabled', function (): void {
    config()->set('attributes.history.enabled', true);

    $revision = $this->action->execute($this->product, new RevisionData(
        name: 'color',
        type: RevisionType::Attached,
        oldValue: null,
        newValue: 'white',
        oldValueType: null,
        newValueType: AttributeType::String_,
    ));

    expect($revision)->not->toBeNull()
        ->and($revision?->name)->toBe('color')
        ->and($revision?->type)->toBe(RevisionType::Attached)
        ->and($revision?->new_value)->toBe('white')
        ->and(AttributeRevision::query()->count())->toBe(1);
});
