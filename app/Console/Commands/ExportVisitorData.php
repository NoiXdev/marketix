<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\Analytics\VisitorData;
use Illuminate\Console\Command;

class ExportVisitorData extends Command
{
    protected $signature = 'marketix:visitor:export
        {site : Tracking ID of the site}
        {visitor : Visitor ID from the visitor\'s mx_vid cookie}';

    protected $description = 'Print everything stored about one visitor as JSON (GDPR access request)';

    public function handle(): int
    {
        $site = Site::withTrashed()->where('tracking_id', $this->argument('site'))->first();
        if ($site === null) {
            $this->error('No site with this tracking ID.');

            return self::FAILURE;
        }

        $data = VisitorData::for($site, (string) $this->argument('visitor'));

        if (array_sum($data->counts()) === 0) {
            $this->warn('No data stored for this visitor.');

            return self::SUCCESS;
        }

        $this->line((string) json_encode($data->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
