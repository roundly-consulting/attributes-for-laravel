<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Actions;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Support\AttributeModel;
use RoundlyConsulting\Attributes\Support\AttributesConfig;

final readonly class PruneAttributesAction
{
    /**
     * Permanently delete soft-deleted attributes trashed more than `$days` days
     * ago (default: `attributes.prune_after_days`). Returns how many were removed.
     */
    public function execute(?int $days = null): int
    {
        $model = AttributeModel::class();

        return (int) $model::onlyTrashed()
            ->where('deleted_at', '<', Carbon::now()->subDays($days ?? AttributesConfig::pruneAfterDays()))
            ->forceDelete();
    }
}
