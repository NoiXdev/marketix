<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithProject(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);

        return [$user, $project];
    }

    public function test_a_domain_used_by_another_project_cannot_be_added(): void
    {
        [$user, $project] = $this->userWithProject();
        Domain::create(['project_id' => Project::create(['name' => 'Other'])->id, 'name' => 'shop.test']);

        $this->actingAs($user)
            ->post(route('app.project.domains.store', ['project' => $project->id]), ['name' => ' Shop.TEST '])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, $project->domains()->count());
    }

    public function test_names_are_stored_in_lowercase(): void
    {
        [$user, $project] = $this->userWithProject();

        $this->actingAs($user)
            ->post(route('app.project.domains.store', ['project' => $project->id]), ['name' => ' Links.Example.TEST '])
            ->assertSessionHasNoErrors();

        $this->assertSame('links.example.test', $project->domains()->value('name'));
    }

    public function test_the_name_of_a_deleted_domain_can_be_used_again(): void
    {
        [$user, $project] = $this->userWithProject();
        Domain::create(['project_id' => $project->id, 'name' => 'shop.test'])->delete();

        $this->actingAs($user)
            ->post(route('app.project.domains.store', ['project' => $project->id]), ['name' => 'shop.test'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $project->domains()->count());
    }

    public function test_a_domain_can_be_saved_with_its_own_name(): void
    {
        [$user, $project] = $this->userWithProject();
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'shop.test']);

        $this->actingAs($user)
            ->put(route('app.project.domains.update', ['project' => $project->id, 'domain' => $domain->id]), ['name' => 'shop.test'])
            ->assertSessionHasNoErrors();
    }

    public function test_the_database_rejects_two_active_domains_with_the_same_name(): void
    {
        [, $project] = $this->userWithProject();
        Domain::create(['project_id' => $project->id, 'name' => 'shop.test']);

        $this->expectException(UniqueConstraintViolationException::class);

        Domain::create(['project_id' => Project::create(['name' => 'Other'])->id, 'name' => 'shop.test']);
    }
}
