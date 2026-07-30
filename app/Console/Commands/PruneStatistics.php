<?php

namespace App\Console\Commands;

use App\Models\Statistic;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class PruneStatistics extends Command
{
    protected $signature = 'statistics:prune';

    protected $description = 'Hard-delete statistics rows older than the configured retention window';

    public function handle(): int
    {
        $months = (int) config('statistics.retention_months', 12);
        $cutoff = now()->subMonths($months)->startOfDay();

        $total = 0;

        // chunkById + forceDelete is DB-portable (SQLite has no DELETE ... LIMIT).
        // Deleting the fetched rows is safe with chunkById because it pages by id.
        Statistic::where('created_at', '<', $cutoff)
            ->select('id')
            ->chunkById(1000, function (Collection $rows) use (&$total) {
                $total += Statistic::whereKey($rows->modelKeys())->forceDelete();
            });

        $this->info("Pruned {$total} statistics rows older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
