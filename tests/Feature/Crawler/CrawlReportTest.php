<?php

namespace Tests\Feature\Crawler;

use App\Crawler\CheckCatalog;
use App\Crawler\IssueCode;
use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class CrawlReportTest extends TestCase
{
    use RefreshDatabase;

    private function member(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);

        return [$user, $project];
    }

    public function test_member_can_download_crawl_report(): void
    {
        Pdf::fake();
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create(['pages_crawled' => 2]);
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/a',
            'issues' => ['missing_title'],
        ]);
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/b',
            'issues' => [],
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.project.crawls.report', ['project' => $project->id, 'crawl' => $crawl->id]));

        $response->assertOk();
        Pdf::assertRespondedWithPdf(function ($pdf) use ($crawl, $project) {
            return $pdf->viewName === 'reports.crawl'
                && $pdf->viewData['crawl']->is($crawl)
                && $pdf->viewData['project']->is($project)
                && is_array($pdf->viewData['score'])
                && array_key_exists('overall', $pdf->viewData['score'])
                && array_key_exists('topActions', $pdf->viewData['score'])
                && $pdf->viewData['score']['topActions'][0]['code'] === 'missing_title';
        });
    }

    public function test_cannot_download_another_projects_crawl_report(): void
    {
        Pdf::fake();
        [$user, $project] = $this->member();
        $other = Project::factory()->create();
        $crawl = Crawl::factory()->for($other)->create();

        $this->actingAs($user)
            ->get(route('app.project.crawls.report', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertNotFound();
    }

    /**
     * Hermetic Blade-render test (mirrors tests/Feature/Reports/ReportViewsTest.php):
     * renders the actual view with a crawl+score fixture — no Pdf::fake(), no
     * Browsershot/Chromium — and asserts every active problem code's label and
     * guidance resolve to real text rather than leaking a raw `crawler.issue...` key.
     */
    public function test_crawl_report_view_renders_without_raw_lang_keys(): void
    {
        $project = Project::factory()->make(['name' => 'Acme Inc.']);
        $crawl = Crawl::factory()->make(['start_url' => 'https://acme.test', 'pages_crawled' => 12]);

        $codes = collect(CheckCatalog::all())
            ->filter(fn (array $entry) => $entry['status'] === 'active' && CheckCatalog::isProblemCode($entry['code']))
            ->pluck('code')
            ->unique()
            ->values();

        $score = [
            'overall' => 87,
            'seo' => 90,
            'geo' => 80,
            'categories' => ['page_title' => 92, 'security' => 70],
            'topActions' => $codes->map(fn (string $code) => [
                'code' => $code,
                'category' => CheckCatalog::categoryOf($code),
                'severity' => IssueCode::tryFrom($code)?->severity() ?? 'notice',
                'count' => 1,
            ])->all(),
        ];

        $html = view('reports.crawl', ['crawl' => $crawl, 'project' => $project, 'score' => $score])->render();

        $this->assertNotSame('', $html);
        $this->assertStringNotContainsString('crawler.issue', $html);
        $this->assertStringContainsString('87', $html);
        $this->assertStringContainsString('Acme Inc.', $html);
    }
}
