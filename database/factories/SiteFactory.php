<?php

namespace Database\Factories;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Site> */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'domain' => $this->faker->domainName(),
            'tracking_mode' => TrackingMode::Cookieless,
            'consent_mode' => ConsentMode::Immediate,
            'consent_signal' => null,
            'respect_dnt' => false,
            'retention_days' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Site $site) {
            if ($site->project_id === null) {
                $site->project_id = Project::create(['name' => $this->faker->company()])->id;
            }
        });
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => ['project_id' => $project->id]);
    }
}
