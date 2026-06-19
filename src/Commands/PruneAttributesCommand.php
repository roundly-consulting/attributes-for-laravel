<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\Models\Attribute;

final class PruneAttributesCommand extends Command
{
    protected $signature = 'attributes:prune {--days= : Permanently remove trashed attributes older than this many days} {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently delete soft-deleted attributes older than the configured age';

    public function handle(): int
    {
        $days = $this->resolveDays();

        $cutoff = Carbon::now()->subDays($days);

        /** @var class-string<Attribute> $model */
        $model = config('attributes.model', Attribute::class);

        $query = $model::onlyTrashed()->where('deleted_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No attributes to prune.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently delete {$count} trashed attribute(s)?")) {
            $this->info('Pruning cancelled.');

            return self::SUCCESS;
        }

        $query->forceDelete();

        $this->info("Pruned {$count} attribute(s).");

        return self::SUCCESS;
    }

    private function resolveDays(): int
    {
        $option = $this->option('days');

        if ($option !== null) {
            return (int) $option;
        }

        $configured = config('attributes.prune_after_days', 30);

        return is_numeric($configured) ? (int) $configured : 30;
    }
}
