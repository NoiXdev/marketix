<?php

namespace Database\Factories;

use App\Models\Annotation;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Annotation> */
class AnnotationFactory extends Factory
{
    protected $model = Annotation::class;

    public function definition(): array
    {
        return [
            'date' => '2026-10-01',
            'title' => 'Relaunch',
            'description' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Annotation $annotation) {
            if ($annotation->site_id === null || $annotation->project_id === null) {
                $site = $annotation->site_id ? Site::find($annotation->site_id) : Site::factory()->create();
                $annotation->site_id = $site->id;
                $annotation->project_id = $site->project_id;
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
