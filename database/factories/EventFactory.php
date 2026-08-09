<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Site;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'visitor_hash' => hash('sha256', $this->faker->uuid()),
            'name' => 'signup',
            'props' => null,
            'path' => '/'.$this->faker->slug(),
            'is_bot' => false,
            'created_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Event $event) {
            if ($event->site_id === null || $event->project_id === null || $event->visit_id === null) {
                $site = $event->site_id ? Site::find($event->site_id) : Site::factory()->create();
                $visit = Visit::factory()->forSite($site)->create();
                $event->visit_id = $visit->id;
                $event->site_id = $site->id;
                $event->project_id = $site->project_id;
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
