<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\Analytics\VisitorData;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ForgetVisitor extends Command
{
    protected $signature = 'marketix:visitor:forget
        {site : Tracking ID of the site}
        {visitor : Visitor ID from the visitor\'s mx_vid cookie}
        {--force : Delete without asking}';

    protected $description = 'Delete everything stored about one visitor (GDPR erasure request)';

    public function handle(): int
    {
        $site = Site::withTrashed()->where('tracking_id', $this->argument('site'))->first();
        if ($site === null) {
            $this->error('No site with this tracking ID.');

            return self::FAILURE;
        }

        $data = VisitorData::for($site, (string) $this->argument('visitor'));
        $counts = $data->counts();

        if (array_sum($counts) === 0) {
            $this->warn('No data stored for this visitor.');

            return self::SUCCESS;
        }

        $summary = collect(['visits' => 'visit', 'page_views' => 'page view', 'events' => 'event'])
            ->map(fn (string $noun, string $key) => $counts[$key].' '.Str::plural($noun, $counts[$key]))
            ->join(', ', ' and ');

        if (! $this->option('force') && ! $this->confirm("Delete {$summary} on {$site->domain}?")) {
            $this->info('Nothing deleted.');

            return self::FAILURE;
        }

        $data->forget();
        $this->info("Deleted {$summary}.");

        return self::SUCCESS;
    }
}
