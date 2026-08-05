<?php

namespace Tests\Feature;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_goal_with_type_cast_and_site_relation(): void
    {
        $site = Site::factory()->create();

        $goal = Goal::create([
            'project_id' => $site->project_id,
            'site_id' => $site->id,
            'name' => 'Signup',
            'type' => GoalType::Event,
            'match_value' => 'signup',
        ]);

        $this->assertInstanceOf(GoalType::class, $goal->fresh()->type);
        $this->assertSame('signup', $goal->match_value);
        $this->assertSame($site->id, $goal->site->id);
        $this->assertTrue($site->goals()->whereKey($goal->id)->exists());
    }

    public function test_goal_type_options(): void
    {
        $this->assertCount(2, GoalType::cases());
        $this->assertContains(['value' => 'pageview', 'label' => GoalType::Pageview->label()], GoalType::options());
    }
}
