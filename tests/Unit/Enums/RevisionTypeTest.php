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
