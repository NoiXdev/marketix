<?php

namespace Tests\Feature;

use App\Models\Statistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticIsBotColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_false_and_casts_to_bool(): void
    {
        $stat = Statistic::factory()->create();

        $this->assertFalse($stat->fresh()->is_bot);
    }

    public function test_bot_state_persists_true(): void
    {
        $stat = Statistic::factory()->bot()->create();

        $this->assertTrue($stat->fresh()->is_bot);
        $this->assertDatabaseHas('statistics', ['id' => $stat->id, 'is_bot' => true]);
    }
}
