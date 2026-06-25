<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Statistic;
use App\Services\StatisticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryCodeBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_by_country_code_with_name_and_count(): void
    {
        $project = Project::create(['name' => 'Acme']);

        Statistic::factory()->count(3)->forProject($project)
            ->state(['country' => 'Germany', 'country_code' => 'DE'])->create();
        Statistic::factory()->count(1)->forProject($project)
            ->state(['country' => 'France', 'country_code' => 'FR'])->create();
        // Null code rows must be excluded.
        Statistic::factory()->count(5)->forProject($project)
            ->state(['country' => 'Nowhere', 'country_code' => null])->create();

        $rows = app(StatisticsAggregator::class)
            ->breakdownByCountryCode($project->id, null);

        $this->assertCount(2, $rows);
        $top = $rows->first();
        $this->assertSame('DE', $top->country_code);
        $this->assertSame('Germany', $top->country);
        $this->assertSame(3, (int) $top->count);
    }
}
