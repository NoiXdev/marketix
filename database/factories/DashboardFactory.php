<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DashboardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => fn () => Project::create(['name' => $this->faker->company()])->id,
            'name' => 'Overview',
            'is_default' => false,
            'position' => 0,
            'widgets' => [],
        ];
    }
}
