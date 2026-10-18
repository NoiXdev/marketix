<?php

namespace Database\Factories;

use App\Enums\PixelProvider;
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
            'project_id' => Project::factory(),
            'provider' => $this->faker->randomElement(PixelProvider::cases()),
            'name' => $this->faker->words(2, true),
            'tag' => $this->faker->uuid(),
        ];
    }
}
