<?php

namespace Database\Factories;

use App\Models\PageView;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageView> */
class PageViewFactory extends Factory
{
    protected $model = PageView::class;

    public function definition(): array
    {
        return [
            'visitor_hash' => hash('sha256', $this->faker->uuid()),
            'path' => '/'.$this->faker->slug(),
            'referer' => null,
            'referer_domain' => null,
            'country' => $this->faker->country(),
            'country_code' => $this->faker->countryCode(),
            'city' => $this->faker->city(),
            'browser' => 'Chrome',
            'os' => 'macOS',
            'device' => 'Desktop',
            'language' => 'en',
            'is_bot' => false,
            'created_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PageView $pv) {
            if ($pv->site_id === null || $pv->project_id === null || $pv->visit_id === null) {
                $site = $pv->site_id ? Site::find($pv->site_id) : Site::factory()->create();
                $visit = Visit::factory()->forSite($site)->create();
                $pv->visit_id = $visit->id;
                $pv->site_id = $site->id;
                $pv->project_id = $site->project_id;
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

    public function forVisit(Visit $visit): static
    {
        return $this->state(fn (array $attributes) => [
            'visit_id' => $visit->id,
            'site_id' => $visit->site_id,
            'project_id' => $visit->project_id,
        ]);
    }
}
