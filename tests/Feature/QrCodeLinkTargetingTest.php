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

    public function test_creating_dynamic_qr_persists_link_settings(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload([
                'domain_id' => $domain->id,
                'slug' => 'promo',
                'status' => 0, // deactivated
                'password' => 'secret',
                'expired_at' => '2030-01-01T00:00',
                'targeting_geo' => [['country' => 'US', 'state' => '', 'url' => 'https://example.com/us']],
                'targeting_device' => [['device' => 'iOS', 'url' => 'https://example.com/ios']],
            ]),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $url = Url::where('slug', 'promo')->firstOrFail();
        $this->assertSame(0, $url->status->value);
        $this->assertNotNull($url->password); // hashed, non-empty
        $this->assertSame('US', $url->targeting_geo[0]['country']);
        $this->assertSame('https://example.com/ios', $url->targeting_device[0]['url']);
        $this->assertNotNull($url->expired_at);
    }

    public function test_updating_dynamic_qr_edits_link_targeting(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo']),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $qr = QrCode::firstOrFail();

        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            $this->payload([
                'domain_id' => $domain->id,
                'slug' => 'promo',
                'targeting_language' => [['language' => 'de', 'url' => 'https://example.com/de']],
            ]),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $url = $qr->fresh()->url;
        $this->assertSame('de', $url->targeting_language[0]['language']);
    }

    public function test_restoring_a_version_leaves_link_targeting_untouched(): void
    {
        [$user, $project, $domain] = $this->tenant();

        // v1: create with geo targeting.
        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload([
                'domain_id' => $domain->id, 'slug' => 'promo',
                'targeting_geo' => [['country' => 'US', 'state' => '', 'url' => 'https://example.com/us']],
            ]),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();
        $qr = QrCode::firstOrFail();

        // v2: edit the QR name (creates a second version).
        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            $this->payload([
                'name' => 'Renamed', 'domain_id' => $domain->id, 'slug' => 'promo',
                'targeting_geo' => [['country' => 'US', 'state' => '', 'url' => 'https://example.com/us']],
            ]),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        // Restore v1.
        $this->actingAs($user)->post(
            route('app.project.qrcodes.versions.restore', ['project' => $project->id, 'qrCode' => $qr->id, 'version' => 1]),
        )->assertSessionHasNoErrors();

        // The link's geo targeting is still present (restore does not clear it).
        $url = $qr->fresh()->url;
        $this->assertSame('US', $url->targeting_geo[0]['country']);
    }
}
