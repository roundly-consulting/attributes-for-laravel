<?php

declare(strict_types=1);

use RoundlyConsulting\Attributes\Facades\Attributes;

/*
 * The facade contract, pinned: the docblock matches AttributesManager's public API and the
 * accessor is its class-string; fake() is real, an AttributesManager subtype, and takes over
 * DI too; and every host-facing action under src/Actions is reachable from the facade (8 of 11 —
 * RecordAttributeRevisionAction, WriteAttributeAction and WriteAttributeSetAction are @internal
 * building blocks of the attach/sync/detach actions).
 */
it('keeps the facade complete, fakeable and covering every action', function (): void {
    expect(Attributes::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});
