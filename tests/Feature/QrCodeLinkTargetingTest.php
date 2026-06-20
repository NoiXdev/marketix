<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeLinkTargetingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Project, 2: Domain} */
    private function tenant(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return [$user, $project, $domain];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'My QR',
            'type' => 'link',
            'is_dynamic' => true,
            'content' => ['url' => 'https://example.com/landing'],
            'style' => [
                'foreground' => '#000000', 'background' => '#ffffff',
                'dot_style' => 'square', 'corner_square_style' => 'square',
                'corner_dot_style' => 'square', 'logo_type' => 'none',
                'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
            ],
        ], $overrides);
    }

    public function test_invalid_geo_targeting_rule_is_rejected(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $response = $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload([
                'domain_id' => $domain->id,
                'slug' => 'promo',
                // Missing the required per-rule url.
                'targeting_geo' => [['country' => 'US', 'state' => '']],
            ]),
            ['X-Inertia' => 'true'],
        );

        $response->assertJsonValidationErrors(['targeting_geo.0.url']);
    }
}
