<?php

namespace Tests\Feature\Crawler;

use App\Crawler\Export\CrawlXlsxExporter;
use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class CrawlXlsxExportTest extends TestCase
{
    use RefreshDatabase;

    private function member(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);

        return [$user, $project];
    }

    public function test_export_xlsx_returns_a_downloadable_workbook(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/a',
            'issues' => ['missing_title', 'missing_csp_header'],
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.project.crawls.export-xlsx', ['project' => $project->id, 'crawl' => $crawl->id]));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("crawl-{$crawl->id}.xlsx", $response->headers->get('Content-Disposition'));
    }

    public function test_overview_and_category_sheets_reflect_only_problem_severity_codes(): void
    {
        app()->setLocale('en');

        [, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();

        // A: two real problems, in two different categories (page_title, security).
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/a',
            'issues' => ['missing_title', 'missing_csp_header'],
            'is_indexable' => true,
            'inlinks_count' => 3,
            'status_code' => 200,
            'depth' => 1,
        ]);

        // B: an info-severity code only (https_urls) — must NOT count as a problem.
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/b',
            'issues' => ['https_urls'],
            'status_code' => 200,
        ]);

        // C: no issues at all.
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/c',
            'issues' => [],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'crawl-xlsx-test-');

        try {
            app(CrawlXlsxExporter::class)->writeToFile($crawl, $path);

            $rows = $this->readWorkbook($path);

            $overviewName = __('crawler.export_overview');
            $this->assertArrayHasKey($overviewName, $rows);

            $overview = $rows[$overviewName];
            $overviewByCategory = [];
            foreach (array_slice($overview, 1) as $row) {
                $overviewByCategory[$row[0]] = $row[1];
            }

            $pageTitleLabel = __('crawler.category_group.page_title');
            $securityLabel = __('crawler.category_group.security');

            $this->assertSame(1, $overviewByCategory[$pageTitleLabel] ?? null);
            $this->assertSame(1, $overviewByCategory[$securityLabel] ?? null);

            // No category with zero affected pages gets a sheet — the overview only
            // lists page_title and security, so no other category sheet should exist.
            $expectedSheetNames = [$overviewName, $pageTitleLabel, $securityLabel];
            foreach (array_keys($rows) as $sheetName) {
                $this->assertContains($sheetName, $expectedSheetNames, "Unexpected sheet: {$sheetName}");
            }

            // Page Titles sheet: contains A with the missing_title label, but not B or C.
            $pageTitleSheet = $rows[$pageTitleLabel];
            $urls = array_column(array_slice($pageTitleSheet, 1), 0);
            $this->assertContains('https://example.com/a', $urls);
            $this->assertNotContains('https://example.com/b', $urls);
            $this->assertNotContains('https://example.com/c', $urls);

            $missingTitleLabel = __('crawler.issue.missing_title');
            $aRow = collect(array_slice($pageTitleSheet, 1))->firstWhere(0, 'https://example.com/a');
            $this->assertStringContainsString($missingTitleLabel, $aRow[4]);

            // Security sheet: contains A (missing_csp_header), but NOT B (https_urls is info-only).
            $securitySheet = $rows[$securityLabel];
            $securityUrls = array_column(array_slice($securitySheet, 1), 0);
            $this->assertContains('https://example.com/a', $securityUrls);
            $this->assertNotContains('https://example.com/b', $securityUrls);
        } finally {
            @unlink($path);
        }
    }

    /** @return array<string, array<int, array<int, mixed>>> */
    private function readWorkbook(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $name = $sheet->getName();
            foreach ($sheet->getRowIterator() as $row) {
                $rows[$name][] = $row->toArray();
            }
        }

        $reader->close();

        return $rows;
    }
}
