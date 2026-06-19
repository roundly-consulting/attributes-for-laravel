<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Attributes\DataTransferObjects\RevisionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Enums\RevisionType;

it('holds its revision fields', function (): void {
    $data = new RevisionData(
        name: 'color',
        type: RevisionType::Updated,
        oldValue: 'white',
        newValue: 'black',
        oldValueType: AttributeType::String_,
        newValueType: AttributeType::String_,
        oldMeta: new Collection(['source' => 'old']),
        newMeta: new Collection(['source' => 'new']),
    );

    expect($data->name)->toBe('color')
        ->and($data->type)->toBe(RevisionType::Updated)
        ->and($data->oldValue)->toBe('white')
        ->and($data->newValue)->toBe('black')
        ->and($data->oldValueType)->toBe(AttributeType::String_)
        ->and($data->newValueType)->toBe(AttributeType::String_)
        ->and($data->oldMeta?->get('source'))->toBe('old')
        ->and($data->newMeta?->get('source'))->toBe('new');
});

it('defaults meta to null', function (): void {
    $data = new RevisionData(
        name: 'color',
        type: RevisionType::Attached,
        oldValue: null,
        newValue: 'white',
        oldValueType: null,
        newValueType: AttributeType::String_,
    );

    expect($data->oldMeta)->toBeNull()
        ->and($data->newMeta)->toBeNull();
});
