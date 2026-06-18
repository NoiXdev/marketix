<?php

namespace Tests\Feature;

use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Project;
use App\Models\Statistic;
use App\Services\StatisticsAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StatisticsAggregatorRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DomainObserver dispatches this on creation; it writes a Traefik
        // config file to disk, which we don't want in tests.
        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    public function test_clicks_by_day_between_zero_fills_and_bounds(): void
    {
        $project = Project::factory()->create();

        Statistic::factory()->forProject($project)->create(['created_at' => '2026-04-02 10:00:00', 'ip' => '1.1.1.1']);
        Statistic::factory()->forProject($project)->create(['created_at' => '2026-04-02 11:00:00', 'ip' => '1.1.1.1']);
        Statistic::factory()->forProject($project)->create(['created_at' => '2026-04-03 10:00:00', 'ip' => '2.2.2.2']);
        // Outside the range — must be excluded.
        Statistic::factory()->forProject($project)->create(['created_at' => '2026-04-10 10:00:00', 'ip' => '9.9.9.9']);

        $stats = new StatisticsAggregator;
        $rows = $stats->clicksByDayBetween(
            $project->id,
            null,
            CarbonImmutable::parse('2026-04-01'),
            CarbonImmutable::parse('2026-04-03'),
        );

        $this->assertCount(3, $rows);
        $this->assertSame(['date' => '2026-04-01', 'clicks' => 0, 'unique' => 0], $rows[0]);
        $this->assertSame(['date' => '2026-04-02', 'clicks' => 2, 'unique' => 1], $rows[1]);
        $this->assertSame(['date' => '2026-04-03', 'clicks' => 1, 'unique' => 1], $rows[2]);
    }

    public function test_total_clicks_respects_until_upper_bound(): void
    {
        $project = Project::factory()->create();
        Statistic::factory()->forProject($project)->create(['created_at' => '2026-04-02 10:00:00']);
        Statistic::factory()->forProject($project)->create(['created_at' => '2026-04-20 10:00:00']);

        $stats = new StatisticsAggregator;

        $this->assertSame(1, $stats->totalClicks(
            $project->id,
            null,
            CarbonImmutable::parse('2026-04-01 00:00:00'),
            CarbonImmutable::parse('2026-04-03 23:59:59'),
        ));
    }
}
