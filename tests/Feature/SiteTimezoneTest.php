<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Visit;
use App\Services\AnalyticsAggregator;
use App\Support\Analytics\AnalyticsQuery;
use App\Support\Analytics\PeriodResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SiteTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function viewAt(Site $site, string $utc): void
    {
        $visit = Visit::factory()->forSite($site)->create(['started_at' => $utc, 'last_activity_at' => $utc]);
        PageView::factory()->forVisit($visit)->create(['created_at' => $utc]);
    }

    /** @return array<string, int> */
    private function viewsByDay(Site $site, string $from, string $to): array
    {
        $query = AnalyticsQuery::between(
            CarbonImmutable::parse($from, $site->timezone),
            CarbonImmutable::parse($to, $site->timezone)->endOfDay(),
            [],
            'day',
        );

        return array_column(app(AnalyticsAggregator::class)->timeseries($site->id, $query), 'views', 'date');
    }

    public function test_days_follow_the_site_time_zone(): void
    {
        $site = Site::factory()->create(['timezone' => 'Europe/Zurich']);
        $this->viewAt($site, '2026-01-10 23:30:00');

        $views = $this->viewsByDay($site, '2026-01-10', '2026-01-11');

        $this->assertSame(['2026-01-10' => 0, '2026-01-11' => 1], $views);
    }

    public function test_days_stay_correct_across_a_daylight_saving_change(): void
    {
        $site = Site::factory()->create(['timezone' => 'Europe/Zurich']);
        $this->viewAt($site, '2026-03-28 23:30:00'); // 00:30 CET on the 29th
        $this->viewAt($site, '2026-03-30 22:30:00'); // 00:30 CEST on the 31st
        $this->viewAt($site, '2026-03-30 21:30:00'); // 23:30 CEST on the 30th

        $views = $this->viewsByDay($site, '2026-03-28', '2026-03-31');

        $this->assertSame(['2026-03-28' => 0, '2026-03-29' => 1, '2026-03-30' => 1, '2026-03-31' => 1], $views);
    }

    public function test_timestamps_stored_in_another_app_time_zone_are_converted(): void
    {
        config(['app.timezone' => 'Europe/Zurich']);
        $site = Site::factory()->create(['timezone' => 'America/New_York']);
        $this->viewAt($site, '2026-01-11 01:30:00'); // Zurich time, 19:30 on the 10th in New York

        $views = $this->viewsByDay($site, '2026-01-10', '2026-01-11');

        $this->assertSame(['2026-01-10' => 1, '2026-01-11' => 0], $views);
    }

    public function test_today_starts_at_local_midnight(): void
    {
        $now = CarbonImmutable::parse('2026-06-15 01:00:00', 'UTC');
        $period = PeriodResolver::resolve(['range' => 'today'], [], $now->setTimezone('America/New_York'));

        $this->assertSame('2026-06-14', $period->toArray()['from']);
        $this->assertSame('2026-06-14 04:00:00', $period->query->storageRange()[0]->format('Y-m-d H:i:s'));
    }

    public function test_the_dashboard_counts_today_in_the_site_time_zone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 01:00:00', 'UTC'));
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create(['timezone' => 'America/New_York']);
        $this->viewAt($site, '2026-06-15 00:30:00'); // 20:30 on the 14th in New York
        $this->viewAt($site, '2026-06-14 03:30:00'); // 23:30 on the 13th in New York

        $this->actingAs($user)
            ->get(route('app.project.analytics.show', ['project' => $project->id, 'site' => $site->id, 'range' => 'today']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('site.timezone', 'America/New_York')
                ->where('period.from', '2026-06-14')
                ->where('summary.page_views', 1)
            );
    }

    public function test_the_time_zone_is_saved_and_validated(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $site = Site::factory()->forProject($project)->create();
        $payload = ['name' => $site->name, 'domain' => $site->domain, 'tracking_mode' => 'cookieless', 'consent_mode' => 'immediate'];
        $url = route('app.project.sites.update', ['project' => $project->id, 'site' => $site->id]);

        $this->actingAs($user)->put($url, [...$payload, 'timezone' => 'Europe/Zurich'])->assertSessionHasNoErrors();
        $this->assertSame('Europe/Zurich', $site->fresh()->timezone);

        $this->actingAs($user)->put($url, [...$payload, 'timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');
        $this->assertSame('Europe/Zurich', $site->fresh()->timezone);
    }
}
