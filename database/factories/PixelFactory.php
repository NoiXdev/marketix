<?php

namespace Database\Factories;

use App\Models\Pixel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pixel> */
class PixelFactory extends Factory
{
    protected $model = Pixel::class;

    public function definition(): array
    {
        return [
            // Verified against App\Enums\PixelProvider (the enum PixelRequest
            // validates 'provider' against via Rule::in(array_column(...))).
            'provider' => 'google_analytics',
            'name' => $this->faker->words(2, true),
            'tag' => 'G-'.strtoupper($this->faker->bothify('??######')),
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => ['project_id' => $project->id]);
    }
}
