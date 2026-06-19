<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeData;

it('constructs with defaults', function (): void {
    $data = new AttributeData('color');

    expect($data->name)->toBe('color')
        ->and($data->value)->toBeNull()
        ->and($data->meta)->toBeNull();
});

it('builds via make', function (): void {
    $data = AttributeData::make('rating', 5, collect(['source' => 'admin']));

    expect($data->name)->toBe('rating')
        ->and($data->value)->toBe(5)
        ->and($data->meta?->get('source'))->toBe('admin');
});

it('maps a name => value array into a list of dtos', function (): void {
    $list = AttributeData::collection([
        'color' => 'white',
        'rating' => 5,
    ]);

    expect($list)->toHaveCount(2)
        ->and($list[0]->name)->toBe('color')
        ->and($list[0]->value)->toBe('white')
        ->and($list[1]->name)->toBe('rating')
        ->and($list[1]->value)->toBe(5);
});

it('returns an empty list for an empty map', function (): void {
    expect(AttributeData::collection([]))->toBe([]);
});
