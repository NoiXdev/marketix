<?php

namespace Database\Factories;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Goal> */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'name' => 'Signup',
            'type' => GoalType::Event,
            'match_value' => 'signup',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Goal $goal) {
            if ($goal->site_id === null || $goal->project_id === null) {
                $site = Site::factory()->create();
                $goal->site_id = $site->id;
                $goal->project_id = $site->project_id;
            }
        });
    }

    public function forSite(Site $site): static
    {
        return $this->state(fn (array $attributes) => [
            'site_id' => $site->id,
            'project_id' => $site->project_id,
        ]);
    }
}
