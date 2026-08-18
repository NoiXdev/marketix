<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\QrTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QrTemplate> */
class QrTemplateFactory extends Factory
{
    protected $model = QrTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'style' => QrCodeFactory::defaultStyle(),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => ['project_id' => $project->id]);
    }
}
