<?php

namespace Tests\Feature\Reports;

use App\Enums\GoalType;
use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Models\Visit;
use App\Reports\LinkReport;
use App\Reports\ProjectSummaryReport;
use App\Reports\ReportDateRange;
use App\Reports\ReportTypeRegistry;
use App\Reports\SiteAnalyticsReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ReportTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-06-18 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function window(): ReportDateRange
    {
        $end = CarbonImmutable::now();

        return ReportDateRange::custom($end->subDays(6), $end);
    }

    private function createUrl(Project $project, string $slug): Url
    {
        $user = User::factory()->create();
        $domain = Domain::create([
            'project_id' => $project->id,
            'name' => "{$slug}.test",
        ]);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'url' => "https://{$slug}.example.test",
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'targeting_geo' => [],
            'targeting_device' => [],
            'targeting_language' => [],
            'targeting_ab' => [],
        ]);
    }

    // --- Registry -----------------------------------------------------

    public function test_registry_resolves_all_three_types_and_throws_on_unknown_key(): void
    {
        $registry = app(ReportTypeRegistry::class);

        $this->assertSame(['project_summary', 'link', 'site_analytics'], array_keys($registry->all()));
        $this->assertInstanceOf(ProjectSummaryReport::class, $registry->for('project_summary'));
        $this->assertInstanceOf(LinkReport::class, $registry->for('link'));
        $this->assertInstanceOf(SiteAnalyticsReport::class, $registry->for('site_analytics'));

        $this->expectException(InvalidArgumentException::class);
        $registry->for('bad');
    }

    // --- ProjectSummaryReport ------------------------------------------

    public function test_project_summary_report_validates_subject(): void
    {
        $project = Project::factory()->create();
        $url = $this->createUrl($project, 'go');
        $report = app(ReportTypeRegistry::class)->for('project_summary');

        $this->assertTrue($report->validateSubject($project, null));
        $this->assertFalse($report->validateSubject($project, $url->id));
        $this->assertNull($report->subjectLabel($project, null));
    }

    public function test_project_summary_report_view_data_has_expected_keys_and_title(): void
    {
        $project = Project::factory()->create(['name' => 'Acme']);
        $url = $this->createUrl($project, 'go');
        $range = $this->window();

        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '1'), 'country' => 'Germany', 'browser' => 'Chrome', 'os' => 'macOS', 'domain' => 'ref-a.test']);
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '2'), 'country' => 'Germany', 'browser' => 'Firefox', 'os' => 'Windows', 'domain' => 'ref-b.test']);
        Statistic::factory()->forUrl($url)->bot()->create(['visitor_hash' => hash('sha256', '3')]);

        $report = app(ReportTypeRegistry::class)->for('project_summary');
        $this->assertSame('Acme', $report->title($project, null));

        $data = $report->viewData($project, null, $range);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('totalClicks', $data);
        $this->assertArrayHasKey('uniqueClicks', $data);
        $this->assertArrayHasKey('timeSeries', $data);
        $this->assertArrayHasKey('breakdowns', $data);
        $this->assertArrayHasKey('topLinks', $data);
        $this->assertSame(2, $data['totalClicks']); // bot click excluded
        $this->assertCount(7, $data['timeSeries']);
        $this->assertArrayHasKey('country', $data['breakdowns']);

        $this->assertSame('reports.project', $report->pdfView());
        $this->assertSame('reports.email.project', $report->emailView());

        $csv = $report->csv($data);
        $this->assertIsString($csv);
        $this->assertStringContainsString('Total clicks', $csv);
    }

    // --- LinkReport ------------------------------------------------------

    public function test_link_report_validates_subject_against_project(): void
    {
        $project = Project::factory()->create();
        $url = $this->createUrl($project, 'go');

        $otherProject = Project::factory()->create();
        $otherUrl = $this->createUrl($otherProject, 'foreign');

        $report = app(ReportTypeRegistry::class)->for('link');

        $this->assertTrue($report->validateSubject($project, $url->id));
        $this->assertFalse($report->validateSubject($project, $otherUrl->id));
        $this->assertFalse($report->validateSubject($project, null));
        $this->assertSame('go', $report->subjectLabel($project, $url->id));
    }

    public function test_link_report_view_data_has_expected_keys_and_title(): void
    {
        $project = Project::factory()->create();
        $url = $this->createUrl($project, 'go');
        $other = $this->createUrl($project, 'other');
        $range = $this->window();

        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '1'), 'country' => 'Germany']);
        Statistic::factory()->forUrl($other)->create(['visitor_hash' => hash('sha256', '2')]);

        $report = app(ReportTypeRegistry::class)->for('link');
        $this->assertSame('Link report — /go', $report->title($project, $url->id));

        $data = $report->viewData($project, $url->id, $range);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('totalClicks', $data);
        $this->assertArrayHasKey('timeSeries', $data);
        $this->assertArrayHasKey('breakdowns', $data);
        $this->assertArrayHasKey('recentClicks', $data);
        $this->assertSame(1, $data['totalClicks']);
        $this->assertCount(7, $data['timeSeries']);

        $this->assertSame('reports.link', $report->pdfView());
        $this->assertSame('reports.email.link', $report->emailView());

        $csv = $report->csv($data);
        $this->assertIsString($csv);
        $this->assertNotSame('', $csv);
    }

    // --- SiteAnalyticsReport ---------------------------------------------

    public function test_site_analytics_report_validates_subject_against_project(): void
    {
        $project = Project::factory()->create();
        $site = Site::factory()->forProject($project)->create();

        $otherProject = Project::factory()->create();
        $otherSite = Site::factory()->forProject($otherProject)->create();

        $report = app(ReportTypeRegistry::class)->for('site_analytics');

        $this->assertTrue($report->validateSubject($project, $site->id));
        $this->assertFalse($report->validateSubject($project, $otherSite->id));
        $this->assertFalse($report->validateSubject($project, null));
        $this->assertSame($site->name, $report->subjectLabel($project, $site->id));
    }

    public function test_site_analytics_report_view_data_has_expected_keys_and_title(): void
    {
        $project = Project::factory()->create();
        $site = Site::factory()->forProject($project)->create(['name' => 'Marketing site']);
        $range = $this->window();

        $visit = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($visit)->create();
        PageView::factory()->forVisit($visit)->create();
        Event::factory()->forVisit($visit)->create(['name' => 'signup']);

        $goal = Goal::factory()->forSite($site)->create([
            'name' => 'Signup',
            'type' => GoalType::Event,
            'match_value' => 'signup',
        ]);

        $report = app(ReportTypeRegistry::class)->for('site_analytics');
        $this->assertSame('Site analytics report — Marketing site', $report->title($project, $site->id));

        $data = $report->viewData($project, $site->id, $range);

        $this->assertArrayHasKey('visits', $data);
        $this->assertArrayHasKey('page_views', $data);
        $this->assertArrayHasKey('goals', $data);
        $this->assertArrayHasKey('top_events', $data);
        $this->assertArrayHasKey('series', $data);
        $this->assertSame(2, $data['page_views']);
        $this->assertCount(7, $data['series']);
        $this->assertSame($goal->name, $data['goals'][0]['name']);
        $this->assertSame('signup', $data['top_events'][0]['name']);

        $this->assertSame('reports.site', $report->pdfView());
        $this->assertSame('reports.email.site', $report->emailView());

        $csv = $report->csv($data);
        $this->assertIsString($csv);
        $this->assertStringContainsString('Visits', $csv);
    }
}
