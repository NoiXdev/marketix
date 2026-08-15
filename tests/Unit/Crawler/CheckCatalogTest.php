<?php

namespace Tests\Unit\Crawler;

use App\Crawler\CheckCatalog;
use App\Crawler\IssueCategory;
use App\Crawler\IssueCode;
use PHPUnit\Framework\TestCase;

class CheckCatalogTest extends TestCase
{
    public function test_every_entry_is_well_formed(): void
    {
        $categories = array_map(fn ($c) => $c->value, IssueCategory::cases());

        foreach (CheckCatalog::all() as $entry) {
            $this->assertArrayHasKey('code', $entry);
            $this->assertContains($entry['category'], $categories, $entry['code']);
            $this->assertContains($entry['severity'], ['error', 'warning', 'notice', 'info'], $entry['code']);
            $this->assertContains($entry['status'], ['active', 'planned'], $entry['code']);
        }
    }

    public function test_codes_are_unique(): void
    {
        $codes = array_column(CheckCatalog::all(), 'code');
        $this->assertSame($codes, array_values(array_unique($codes)));
    }

    public function test_every_issue_code_is_an_active_catalogue_entry(): void
    {
        $active = CheckCatalog::activeCodes();

        foreach (IssueCode::cases() as $case) {
            $this->assertContains($case->value, $active, "IssueCode {$case->value} must be an active catalogue check");
            // category() agrees with the catalogue
            $this->assertSame(CheckCatalog::categoryOf($case->value), $case->category()->value, $case->value);
        }
    }

    public function test_active_codes_are_all_real_issue_codes(): void
    {
        foreach (CheckCatalog::all() as $entry) {
            if ($entry['status'] === 'active') {
                $this->assertNotNull(IssueCode::tryFrom($entry['code']), "active code {$entry['code']} has no IssueCode case");
            }
        }
    }

    public function test_active_codes_for_category_filters(): void
    {
        $this->assertContains('missing_title', CheckCatalog::activeCodesForCategory('page_title'));
        $security = CheckCatalog::activeCodesForCategory('security');
        $this->assertContains('mixed_content', $security);
        $this->assertContains('https_urls', $security);
        $this->assertCount(13, $security);

        $url = CheckCatalog::activeCodesForCategory('url');
        $this->assertContains('url_uppercase', $url);
        $this->assertNotContains('url_parameters', $url); // stays planned
        $this->assertCount(8, $url);

        $responseCodes = CheckCatalog::activeCodesForCategory('response_codes');
        $this->assertContains('internal_redirect_3xx', $responseCodes);
        $this->assertNotContains('internal_success_2xx', $responseCodes); // stays planned
        $this->assertCount(9, $responseCodes);

        $this->assertCount(9, CheckCatalog::activeCodesForCategory('page_title'));
        $this->assertCount(8, CheckCatalog::activeCodesForCategory('meta_description'));
        $this->assertCount(3, CheckCatalog::activeCodesForCategory('meta_keywords'));
        $this->assertCount(6, CheckCatalog::activeCodesForCategory('h1'));
        $this->assertCount(5, CheckCatalog::activeCodesForCategory('h2'));
        $this->assertCount(12, CheckCatalog::activeCodesForCategory('canonicals'));
        $this->assertCount(10, CheckCatalog::activeCodesForCategory('pagination'));
        $this->assertCount(14, CheckCatalog::activeCodesForCategory('links'));
    }

    public function test_bijection_cardinality_holds(): void
    {
        $this->assertCount(count(IssueCode::cases()), CheckCatalog::activeCodes());
    }

    public function test_catalogue_severity_matches_issue_code_for_all_active_codes(): void
    {
        foreach (CheckCatalog::all() as $entry) {
            if ($entry['status'] !== 'active') {
                continue;
            }
            $this->assertSame(
                IssueCode::from($entry['code'])->severity(),
                $entry['severity'],
                $entry['code'],
            );
        }
    }
}
