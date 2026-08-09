<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class PruneAnalytics extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Hard-delete analytics rows (page_views/visits) older than each site\'s retention window';

    public function handle(): int
    {
        $defaultMonths = (int) config('analytics.retention_months', 24);
        $totalViews = 0;
        $totalVisits = 0;
        $totalEvents = 0;

        Site::withTrashed()->select('id', 'retention_days')->chunkById(200, function (Collection $sites) use ($defaultMonths, &$totalViews, &$totalVisits, &$totalEvents) {
            foreach ($sites as $site) {
                $cutoff = $site->retention_days
                    ? now()->subDays((int) $site->retention_days)->startOfDay()
                    : now()->subMonths($defaultMonths)->startOfDay();

                PageView::where('site_id', $site->id)
                    ->where('created_at', '<', $cutoff)
                    ->select('id')
                    ->chunkById(1000, function (Collection $rows) use (&$totalViews) {
                        $totalViews += PageView::whereKey($rows->modelKeys())->delete();
                    });

                Visit::where('site_id', $site->id)
                    ->where('last_activity_at', '<', $cutoff)
                    ->select('id')
                    ->chunkById(1000, function (Collection $rows) use (&$totalVisits) {
                        $totalVisits += Visit::whereKey($rows->modelKeys())->delete();
                    });

                Event::where('site_id', $site->id)
                    ->where('created_at', '<', $cutoff)
                    ->select('id')
                    ->chunkById(1000, function (Collection $rows) use (&$totalEvents) {
                        $totalEvents += Event::whereKey($rows->modelKeys())->delete();
                    });
            }
        });

        $this->info("Pruned {$totalViews} page_views, {$totalVisits} visits and {$totalEvents} events.");

        return self::SUCCESS;
    }
}
