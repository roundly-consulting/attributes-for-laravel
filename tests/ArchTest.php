<?php

declare(strict_types=1);

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('uses strict types across the package')
    ->expect('RoundlyConsulting\Attributes')
    ->toUseStrictTypes();

it('keeps support, enum and model classes final')
    ->expect([
        'RoundlyConsulting\Attributes\Support',
        'RoundlyConsulting\Attributes\Models',
        'RoundlyConsulting\Attributes\Actions',
    ])
    ->classes()
    ->toBeFinal();

it('backs every enum with a string')
    ->expect('RoundlyConsulting\Attributes\Enums')
    ->toBeStringBackedEnums();
