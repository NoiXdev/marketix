<?php

namespace App\Console\Commands;

use App\Models\Statistic;
use App\Support\CountryCodes;
use Illuminate\Console\Command;

class BackfillStatisticCountryCodes extends Command
{
    protected $signature = 'statistics:backfill-country-codes';

    protected $description = 'Fill country_code for historical statistics rows from their country name';

    public function handle(): int
    {
        $updated = 0;

        // Resolve each distinct name once, then bulk-update matching rows.
        $names = Statistic::query()
            ->whereNull('country_code')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->pluck('country');

        foreach ($names as $name) {
            $code = CountryCodes::toAlpha2($name);
            if ($code === null) {
                continue;
            }

            $updated += Statistic::query()
                ->whereNull('country_code')
                ->where('country', $name)
                ->update(['country_code' => $code]);
        }

        $this->info("Backfilled {$updated} statistics rows.");

        return self::SUCCESS;
    }
}
