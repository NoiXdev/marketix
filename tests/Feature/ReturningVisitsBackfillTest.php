<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\Visit;
use App\Support\Analytics\ReturningVisits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturningVisitsBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_every_visit_after_a_visitors_first_one_as_returning(): void
    {
        $site = Site::factory()->create();
        $other = Site::factory()->create();

        $first = Visit::factory()->forSite($site)->create(['visitor_hash' => 'a', 'started_at' => now()->subDays(3)]);
        $second = Visit::factory()->forSite($site)->create(['visitor_hash' => 'a', 'started_at' => now()->subDay(), 'is_returning' => false]);
        $single = Visit::factory()->forSite($site)->create(['visitor_hash' => 'b', 'started_at' => now()->subDay(), 'is_returning' => true]);
        $elsewhere = Visit::factory()->forSite($other)->create(['visitor_hash' => 'a', 'started_at' => now()]);

        ReturningVisits::backfill();

        $this->assertFalse($first->fresh()->is_returning);
        $this->assertTrue($second->fresh()->is_returning);
        $this->assertFalse($single->fresh()->is_returning);
        $this->assertFalse($elsewhere->fresh()->is_returning);
    }

    public function test_can_be_limited_to_one_site(): void
    {
        $site = Site::factory()->create();
        $other = Site::factory()->create();

        Visit::factory()->forSite($site)->create(['visitor_hash' => 'a', 'started_at' => now()->subDay()]);
        $returning = Visit::factory()->forSite($site)->create(['visitor_hash' => 'a', 'started_at' => now()]);
        Visit::factory()->forSite($other)->create(['visitor_hash' => 'a', 'started_at' => now()->subDay()]);
        $untouched = Visit::factory()->forSite($other)->create(['visitor_hash' => 'a', 'started_at' => now()]);

        ReturningVisits::backfill($site->id);

        $this->assertTrue($returning->fresh()->is_returning);
        $this->assertFalse($untouched->fresh()->is_returning);
    }
}
