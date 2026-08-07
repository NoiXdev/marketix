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
use App\Reports\ReportData;
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

    private function window(): array
    {
        $end = CarbonImmutable::now();

        return [$end->subDays(6), $end];
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

    public function test_project_summary_report_gathers_totals_breakdowns_and_series(): void
    {
        $project = Project::factory()->create(['name' => 'Acme']);
        $url = $this->createUrl($project, 'go');
        [$start, $end] = $this->window();

        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '1'), 'country' => 'Germany', 'browser' => 'Chrome', 'os' => 'macOS', 'domain' => 'ref-a.test']);
        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '2'), 'country' => 'Germany', 'browser' => 'Firefox', 'os' => 'Windows', 'domain' => 'ref-b.test']);
        Statistic::factory()->forUrl($url)->bot()->create(['visitor_hash' => hash('sha256', '3')]);

        $report = app(ReportTypeRegistry::class)->for('project_summary');
        $data = $report->gather($project, null, $start, $end);

        $this->assertInstanceOf(ReportData::class, $data);
        $this->assertSame('Acme', $data->title);
        $this->assertNotSame('', $data->periodLabel);

        $kpiLabels = array_column($data->kpis, 'label');
        $this->assertContains(__('reports.report.total_clicks'), $kpiLabels);
        $this->assertContains(__('reports.report.unique_clicks'), $kpiLabels);
        $totalClicksKpi = collect($data->kpis)->firstWhere('label', __('reports.report.total_clicks'));
        $this->assertSame('2', $totalClicksKpi['value']); // bot click excluded

        // top links + country/browser/os/domain breakdowns = 5 sections
        $this->assertCount(5, $data->breakdowns);
        $titles = array_column($data->breakdowns, 'title');
        $this->assertContains(__('reports.report.top_links'), $titles);
        $this->assertContains(__('reports.report.top_countries'), $titles);

        $this->assertCount(7, $data->series);
        $this->assertSame(2, array_sum(array_column($data->series, 'value')));

        $csv = $report->csvRows($data);
        $this->assertNotEmpty($csv);
        $this->assertIsArray($csv[0]);
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

    public function test_link_report_gathers_totals_and_series_scoped_to_url(): void
    {
        $project = Project::factory()->create();
        $url = $this->createUrl($project, 'go');
        $other = $this->createUrl($project, 'other');
        [$start, $end] = $this->window();

        Statistic::factory()->forUrl($url)->create(['visitor_hash' => hash('sha256', '1'), 'country' => 'Germany']);
        Statistic::factory()->forUrl($other)->create(['visitor_hash' => hash('sha256', '2')]);

        $report = app(ReportTypeRegistry::class)->for('link');
        $data = $report->gather($project, $url->id, $start, $end);

        $this->assertSame('go', $data->title);
        $totalClicksKpi = collect($data->kpis)->firstWhere('label', __('reports.report.total_clicks'));
        $this->assertSame('1', $totalClicksKpi['value']);
        $this->assertCount(4, $data->breakdowns); // no top-links section for a single link
        $this->assertCount(7, $data->series);
        $this->assertSame(1, array_sum(array_column($data->series, 'value')));

        $csv = $report->csvRows($data);
        $this->assertNotEmpty($csv);
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

    public function test_site_analytics_report_gathers_visits_page_views_goals_and_events(): void
    {
        $project = Project::factory()->create();
        $site = Site::factory()->forProject($project)->create(['name' => 'Marketing site']);
        [$start, $end] = $this->window();

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
        $data = $report->gather($project, $site->id, $start, $end);

        $this->assertSame('Marketing site', $data->title);

        $kpiLabels = array_column($data->kpis, 'label');
        $this->assertContains(__('reports.report.visits'), $kpiLabels);
        $this->assertContains(__('reports.report.page_views'), $kpiLabels);
        $pageViewsKpi = collect($data->kpis)->firstWhere('label', __('reports.report.page_views'));
        $this->assertSame('2', $pageViewsKpi['value']);

        $titles = array_column($data->breakdowns, 'title');
        $this->assertContains(__('reports.report.goals'), $titles);
        $this->assertContains(__('reports.report.top_events'), $titles);

        $goalsBreakdown = collect($data->breakdowns)->firstWhere('title', __('reports.report.goals'));
        $this->assertSame($goal->name, $goalsBreakdown['rows'][0]['label']);

        $this->assertCount(7, $data->series);
        $this->assertSame(2, array_sum(array_column($data->series, 'value')));

        $csv = $report->csvRows($data);
        $this->assertNotEmpty($csv);
    }
}
