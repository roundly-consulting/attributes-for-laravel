<?php

declare(strict_types=1);

namespace RoundlyConsulting\Attributes\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Attributes\AttributesManager;
use RoundlyConsulting\Attributes\Support\AttributeModel;
use RoundlyConsulting\Attributes\Support\AttributesConfig;

final class PruneAttributesCommand extends Command
{
    protected $signature = 'attributes:prune {--days= : Permanently remove trashed attributes older than this many days} {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently delete soft-deleted attributes older than the configured age';

    public function handle(AttributesManager $attributes): int
    {
        $days = $this->resolveDays();

        if ($days === null) {
            $this->error('--days must be a whole number of days (0 or more).');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);

        $model = AttributeModel::class();

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

        $pruned = $attributes->prune($days);

        $this->info("Pruned {$pruned} attribute(s).");

        return self::SUCCESS;
    }

    /**
     * The `--days` option, or the configured age. A junk option is null — never `(int)`
     * cast to 0, which would purge every trashed attribute.
     */
    private function resolveDays(): ?int
    {
        $option = $this->option('days');

        if ($option === null) {
            return AttributesConfig::pruneAfterDays();
        }

        return match (true) {
            is_int($option) => $option >= 0 ? $option : null,
            is_string($option) && preg_match('/^\s*\d+\s*$/', $option) === 1 => (int) $option,
            default => null,
        };
    }
}
