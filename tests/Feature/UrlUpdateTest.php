<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UrlUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([RegenerateTraefikConfigJob::class]);
    }

    public function test_clearing_expired_at_persists_null(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        $url = Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
            'expired_at' => '2026-12-31 10:00:00',
        ]);

        $this->assertNotNull($url->fresh()->expired_at);

        $response = $this->actingAs($user)->putJson(
            route('app.project.links.update', ['project' => $project->id, 'url' => $url->id]),
            [
                'domain_id' => $domain->id,
                'slug' => 'promo',
                'url' => 'https://example.com/default',
                'type' => RedirectType::cases()[0]->value,
                'status' => UrlStatus::ACTIVATED->value,
                'password' => '',
                'expired_at' => '',
            ],
            ['X-Inertia' => 'true'],
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertNull($url->fresh()->expired_at, 'expired_at should be cleared to null');
    }

    public function test_factory_seeded_url_can_be_edited(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        // A URL created exactly as the seeder makes them.
        $url = Url::factory()->create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'seeded',
            'expired_at' => '2026-12-31 10:00:00',
        ]);

        // Re-submit the form's data unchanged, except for clearing the expiry —
        // exactly what the edit page sends back. This must validate and save.
        $response = $this->actingAs($user)->putJson(
            route('app.project.links.update', ['project' => $project->id, 'url' => $url->id]),
            [
                'domain_id' => $domain->id,
                'slug' => 'seeded',
                'url' => 'https://example.com/default',
                'type' => RedirectType::cases()[0]->value,
                'status' => UrlStatus::ACTIVATED->value,
                'password' => '',
                'expired_at' => '',
                'targeting_geo' => $url->targeting_geo,
                'targeting_device' => $url->targeting_device,
                'targeting_language' => $url->targeting_language,
                'targeting_ab' => $url->targeting_ab,
            ],
            ['X-Inertia' => 'true'],
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertNull($url->fresh()->expired_at);
    }
}
