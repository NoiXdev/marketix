<?php

namespace App\Rules;

use App\Models\Crawl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Bounds what a demo visitor can make the crawler do. The crawls table is
 * truncated on every reset, so the row count for a host IS the per-cycle
 * counter — no extra bookkeeping is needed.
 */
class DemoCrawlQuota implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (Crawl::query()->whereIn('status', ['queued', 'running'])->count()
            >= (int) config('demo.crawl.max_concurrent')) {
            $fail(__('demo.crawl_concurrent'));

            return;
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));

        if ($host === '') {
            return; // SafeCrawlUrl already reports malformed URLs.
        }

        // Compare hosts exactly (parsed the same way $value was), rather
        // than a "%//host%" LIKE: a substring match would also count a
        // crawl of an unrelated host whose start_url merely *contains*
        // "//$host" somewhere in its path or query string (e.g. a stored
        // redirect target like https://tracker.example/go?to=https://host),
        // wrongly burning this host's quota. The table is bounded by
        // max_per_host * number of distinct hosts per reset cycle, so
        // pulling every start_url for an exact PHP-side comparison is cheap.
        $used = Crawl::query()
            ->pluck('start_url')
            ->filter(fn (string $startUrl): bool => strtolower((string) parse_url($startUrl, PHP_URL_HOST)) === $host)
            ->count();

        if ($used >= (int) config('demo.crawl.max_per_host')) {
            $fail(__('demo.crawl_quota', ['max' => config('demo.crawl.max_per_host')]));
        }
    }
}
