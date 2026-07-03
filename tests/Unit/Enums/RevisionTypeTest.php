<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Enums\RevisionType;

it('exposes the three revision types', function (): void {
    expect(RevisionType::Attached->value)->toBe('attached')
        ->and(RevisionType::Updated->value)->toBe('updated')
        ->and(RevisionType::Detached->value)->toBe('detached');
});

it('resolves a revision type from its backed value', function (): void {
    expect(RevisionType::from('attached'))->toBe(RevisionType::Attached)
        ->and(RevisionType::tryFrom('nope'))->toBeNull();
});

it('exposes the backed values, labels and options', function (): void {
    expect(RevisionType::values()->all())->toBe(['attached', 'updated', 'detached'])
        ->and(RevisionType::labels()->all())->toBe(['Attached', 'Updated', 'Detached'])
        ->and(RevisionType::toOptions()->all())->toBe([
            'attached' => 'Attached',
            'updated' => 'Updated',
            'detached' => 'Detached',
        ])
        ->and(RevisionType::options())->toHaveCount(3);
});

it('exposes the trait membership validation rule', function (): void {
    expect(RevisionType::validationRule())->toBe('in:attached,updated,detached');
});

it('resolves cases by label and name', function (): void {
    expect(RevisionType::fromLabel('Detached'))->toBe(RevisionType::Detached)
        ->and(RevisionType::tryFromName('Updated'))->toBe(RevisionType::Updated);
});
