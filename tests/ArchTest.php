<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Models\Attribute;

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('uses strict types across the package')
    ->expect('RoundlyConsulting\Attributes')
    ->toUseStrictTypes();

// The Attribute model is exempt: `attributes.model` lets a host swap in its own
// subclass, which `final` would make impossible.
it('keeps support, enum and model classes final')
    ->expect([
        'RoundlyConsulting\Attributes\Support',
        'RoundlyConsulting\Attributes\Models',
        'RoundlyConsulting\Attributes\Actions',
    ])
    ->classes()
    ->toBeFinal()
    ->ignoring(Attribute::class);

it('backs every enum with a string')
    ->expect('RoundlyConsulting\Attributes\Enums')
    ->toBeStringBackedEnums();
