<?php

namespace Tests\Feature\Crawler;

use App\Crawler\CheckCatalog;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class CrawlLangCoverageTest extends TestCase
{
    /**
     * Every active, non-info check code must have both a short label
     * (`crawler.issue.<code>`) and a one-sentence customer-facing
     * explanation (`crawler.issue_help.<code>`) in every supported locale.
     * Missing entries leak raw translation keys into the customer PDF
     * report and the page-detail Checks table.
     */
    public function test_every_active_problem_code_has_issue_and_help_labels(): void
    {
        $codes = collect(CheckCatalog::all())
            ->filter(fn (array $entry) => $entry['status'] === 'active' && CheckCatalog::isProblemCode($entry['code']))
            ->pluck('code')
            ->unique()
            ->values();

        $this->assertNotEmpty($codes);

        foreach (['en', 'de'] as $locale) {
            foreach ($codes as $code) {
                $this->assertTrue(
                    Lang::has('crawler.issue.'.$code, $locale),
                    "Missing crawler.issue.{$code} for locale [{$locale}]"
                );
                $this->assertTrue(
                    Lang::has('crawler.issue_help.'.$code, $locale),
                    "Missing crawler.issue_help.{$code} for locale [{$locale}]"
                );
            }
        }
    }
}
