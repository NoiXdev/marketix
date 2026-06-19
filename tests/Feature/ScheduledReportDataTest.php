<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\ReportFrequency;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Reports\ReportPeriod;
use App\Reports\ScheduledReportData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledReportDataTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-06-19 08:00:00');
    }

    private function url(Project $project): Url
    {
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'acme.test']);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => User::factory()->create()->id,
            'slug' => 'go',
            'url' => 'https://example.test',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'targeting_geo' => [], 'targeting_device' => [],
            'targeting_language' => [], 'targeting_ab' => [],
        ]);
    }

    public function test_build_reports_totals_and_percentage_change(): void
    {
        $project = Project::factory()->create();
        $url = $this->url($project);

        // 3 clicks yesterday (current daily period), 2 the day before (previous).
        Statistic::factory()->count(3)->forUrl($url)->create(['created_at' => '2026-06-18 10:00:00']);
        Statistic::factory()->count(2)->forUrl($url)->create(['created_at' => '2026-06-17 10:00:00']);

        $period = ReportPeriod::for(ReportFrequency::Daily, $this->now);
        $payload = app(ScheduledReportData::class)->build($project, $period);

        $this->assertSame(3, $payload['totalClicks']);
        $this->assertGreaterThanOrEqual(1, $payload['uniqueClicks']);
        $this->assertLessThanOrEqual(3, $payload['uniqueClicks']);
        $this->assertSame($payload['uniqueClicks'], $payload['uniqueChange']['value']);
        $this->assertSame(3, $payload['clicksChange']['value']);
        $this->assertSame(2, $payload['clicksChange']['previous']);
        $this->assertSame(50, $payload['clicksChange']['percent']); // (3-2)/2 = +50%
        $this->assertFalse($payload['clicksChange']['isNew']);
        $this->assertSame('18 Jun 2026', $payload['periodLabel']);
        $this->assertSame('Daily', $payload['frequencyLabel']);
    }

    public function test_change_is_flagged_new_when_previous_period_had_no_clicks(): void
    {
        $project = Project::factory()->create();
        $url = $this->url($project);
        Statistic::factory()->count(4)->forUrl($url)->create(['created_at' => '2026-06-18 10:00:00']);

        $period = ReportPeriod::for(ReportFrequency::Daily, $this->now);
        $payload = app(ScheduledReportData::class)->build($project, $period);

        $this->assertSame(4, $payload['clicksChange']['value']);
        $this->assertNull($payload['clicksChange']['percent']);
        $this->assertTrue($payload['clicksChange']['isNew']);
    }

    public function test_change_percent_is_zero_when_both_periods_empty(): void
    {
        $project = Project::factory()->create();
        $this->url($project);

        $period = ReportPeriod::for(ReportFrequency::Daily, $this->now);
        $payload = app(ScheduledReportData::class)->build($project, $period);

        $this->assertSame(0, $payload['totalClicks']);
        $this->assertSame(0, $payload['clicksChange']['percent']);
        $this->assertFalse($payload['clicksChange']['isNew']);
        $this->assertSame([], $payload['topLinks']);
    }
}
