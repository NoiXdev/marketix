<?php

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Funnel> */
class FunnelFactory extends Factory
{
    protected $model = Funnel::class;

    public function definition(): array
    {
        return [
            'name' => 'Checkout',
            'steps' => [
                ['type' => 'pageview', 'value' => '/products/*', 'label' => null],
                ['type' => 'pageview', 'value' => '/cart', 'label' => null],
                ['type' => 'event', 'value' => 'purchase', 'label' => null],
            ],
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Funnel $funnel) {
            if ($funnel->site_id === null || $funnel->project_id === null) {
                $site = $funnel->site_id ? Site::find($funnel->site_id) : Site::factory()->create();
                $funnel->site_id = $site->id;
                $funnel->project_id = $site->project_id;
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
