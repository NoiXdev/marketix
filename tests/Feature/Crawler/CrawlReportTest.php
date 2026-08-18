<?php

namespace Tests\Feature\Crawler;

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
}
