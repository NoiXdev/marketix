<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => \App\Models\Project::factory(),
            'name' => fake()->unique()->domainName(),
            'redirect_root' => null,
            'redirect_not_found' => null,
            'dns_ok' => null,
            'reachable_ok' => null,
            'ssl_ok' => null,
            'check_details' => null,
            'last_checked_at' => null,
        ];
    }
}
