<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Visit> */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'visitor_hash' => hash('sha256', $this->faker->uuid()),
            'started_at' => now(),
            'last_activity_at' => now(),
            'pageview_count' => 1,
            'entry_path' => '/'.$this->faker->slug(),
            'exit_path' => '/'.$this->faker->slug(),
            'country_code' => $this->faker->countryCode(),
            'browser' => 'Chrome',
            'os' => 'macOS',
            'device' => 'Desktop',
            'referer_domain' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_term' => null,
            'utm_content' => null,
            'is_bot' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Visit $visit) {
            if ($visit->site_id === null || $visit->project_id === null) {
                $site = Site::factory()->create();
                $visit->site_id = $site->id;
                $visit->project_id = $site->project_id;
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

    /** @param array<string, string|null> $utm */
    public function withUtm(array $utm = [
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'summer',
        'utm_term' => 'shoes',
        'utm_content' => 'ad-a',
    ]): static
    {
        return $this->state(fn (array $attributes) => $utm);
    }
}
