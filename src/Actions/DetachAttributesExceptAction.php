<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Attributes\Contracts\HasAttributes;

final readonly class DetachAttributesExceptAction
{
    public function __construct(
        private DetachAttributesAction $detachAttributes,
    ) {}

    /**
     * Detach every attached attribute not named in `$keep` — through
     * DetachAttributesAction, so each fires AttributeDetached and records a
     * detached revision. With `$forceDelete`, previously soft-deleted extras
     * are purged too. Returns the names that were detached.
     *
     * @param  Model&HasAttributes  $owner
     * @param  list<string>  $keep
     * @return list<string>
     */
    public function execute(Model $owner, array $keep, bool $forceDelete = false): array
    {
        /** @var list<string> $names */
        $names = $owner->attachedAttributes()
            ->whereNotIn('name', $keep)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        $this->detachAttributes->execute($owner, $names, $forceDelete);

        if ($forceDelete) {
            $owner->attachedAttributes()->onlyTrashed()->whereNotIn('name', $keep)->forceDelete();
        }

        return $names;
    }
}
