<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\DataTransferObjects\AttributeDefinitionData;
use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Attributes\Facades\Attributes;
use RoundlyConsulting\Attributes\Registry\AttributeRegistry;

it('proxies to the registry singleton', function (): void {
    Attributes::flush();

    Attributes::define(new AttributeDefinitionData('rating', AttributeType::Integer));

    expect(Attributes::has('rating'))->toBeTrue()
        ->and(app(AttributeRegistry::class)->has('rating'))->toBeTrue()
        ->and(Attributes::get('rating'))->toBeInstanceOf(AttributeDefinitionData::class);

    Attributes::flush();
});

it('defines many through the facade', function (): void {
    Attributes::flush();

    Attributes::defineMany(
        new AttributeDefinitionData('color', AttributeType::String_),
        new AttributeDefinitionData('rating', AttributeType::Integer),
    );

    expect(Attributes::all())->toHaveCount(2);

    Attributes::flush();
});
