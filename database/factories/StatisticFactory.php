<?php

namespace Database\Factories;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatisticFactory extends Factory
{
    protected $model = Statistic::class;

    public function definition(): array
    {
        // url_id is NOT NULL — create a minimal URL with required dependencies
        // so standalone factory calls (e.g. `for($project)->create()`) work.
        // Callers that supply a real Url should use forUrl() to override both
        // project_id and url_id together.
        $url = $this->createMinimalUrl();

        return [
            'project_id' => $url->project_id,
            'url_id' => $url->id,
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
     * Create a minimal URL (with its required project, domain, user) so that
     * standalone factory calls satisfy the url_id NOT NULL constraint.
     */
    private function createMinimalUrl(): Url
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $domain = Domain::create([
            'project_id' => $project->id,
            'name' => $this->faker->unique()->domainName(),
        ]);

        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => $this->faker->unique()->slug(2),
            'url' => $this->faker->url(),
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'targeting_geo' => [],
            'targeting_device' => [],
            'targeting_language' => [],
            'targeting_ab' => [],
        ]);
    }

    /**
     * Attach the statistic to an existing URL (and its project).
     *
     * project_id and url_id are NOT NULL foreign keys; callers supply a real
     * Url here to ensure both foreign keys are consistent.
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
