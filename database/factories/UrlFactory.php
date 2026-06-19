<?php

namespace Database\Factories;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class UrlFactory extends Factory
{
    protected $model = Url::class;

    public function definition(): array
    {
        return [
            // user_id is required (NOT NULL) and set by UrlObserver to auth()->id()
            // which is null in unauthenticated tests — so we default to a new user.
            'user_id' => User::factory(),
            // domain_id is required (NOT NULL). Default to a new domain so the
            // factory is self-contained. Callers using for($project) get a domain
            // linked to a separate auto-created project (FK is satisfied; there is
            // no cross-column project consistency constraint on the urls table).
            'domain_id' => Domain::factory(),
            'slug' => $this->faker->slug(),
            'url' => $this->faker->url(),
            'type' => $this->faker->randomElement(RedirectType::cases()),
            'password' => bcrypt($this->faker->password()),
            'expired_at' => Carbon::now(),
            'clicks' => $this->faker->randomNumber(),
            'unique_clicks' => $this->faker->randomNumber(),
            'status' => $this->faker->randomElement(UrlStatus::cases()),
            'archived' => $this->faker->boolean(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            // Targeting is stored as an array of rule objects (see UrlRequest
            // and TargetingSection.tsx). Default to none so seeded URLs are
            // valid and editable; targeting-specific tests set their own rules.
            'targeting_geo' => [],
            'targeting_device' => [],
            'targeting_language' => [],
            'targeting_ab' => [],
        ];
    }

    public function named($projectId, $domainId): UrlFactory
    {
        $availableUsersInProject = Project::find($projectId)->users()->pluck('id')->toArray();

        return $this->state(fn (array $attributes) => [
            'project_id' => $projectId,
            'domain_id' => $domainId,
            'user_id' => $this->faker->randomElement($availableUsersInProject),
        ]);
    }
}
