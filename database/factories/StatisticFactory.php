<?php

namespace Database\Factories;

use App\Models\Statistic;
use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatisticFactory extends Factory
{
    protected $model = Statistic::class;

    public function definition(): array
    {
        return [
            'ip' => $this->faker->ipv4(),
            'country' => $this->faker->country(),
            'city' => $this->faker->city(),
            'language' => $this->faker->languageCode(),
            'domain' => $this->faker->domainName(),
            'referer' => $this->faker->url(),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'os' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'Android', 'iOS']),
        ];
    }

    /**
     * Attach the statistic to an existing URL (and its project).
     *
     * project_id and url_id are NOT NULL foreign keys; there are no
     * Project/Domain factories, so callers supply a real Url here.
     */
    public function forUrl(Url $url): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $url->project_id,
            'url_id' => $url->id,
        ]);
    }

    /**
     * Pin the visitor country (handy for seeding dashboard breakdowns).
     */
    public function country(string $country): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => $country,
        ]);
    }
}
