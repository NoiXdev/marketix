<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\Url;
use App\Models\User;
use App\Services\GeoIpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeBackingLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project, 2: Domain}
     */
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

    public function test_dynamic_qr_requires_domain_and_slug(): void
    {
        [$user, $project] = $this->tenant();

        $response = $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(),
            ['X-Inertia' => 'true'],
        );

        $response->assertJsonValidationErrors(['domain_id', 'slug']);
    }

    public function test_dynamic_link_qr_rejects_empty_target(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $response = $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo', 'content' => ['url' => '']]),
            ['X-Inertia' => 'true'],
        );

        $response->assertJsonValidationErrors(['content']);
    }

    public function test_creating_dynamic_qr_creates_linked_backing_url(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo']),
            ['X-Inertia' => 'true'],
        )->assertRedirect(route('app.project.qrcodes.index'));

        $this->assertDatabaseHas('urls', [
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'slug' => 'promo',
            'url' => 'https://example.com/landing',
            'user_id' => $user->id, // set by UrlObserver
        ]);

        $url = Url::where('slug', 'promo')->firstOrFail();
        $this->assertDatabaseHas('qr_codes', [
            'name' => 'My QR',
            'type' => 'link',
            'is_dynamic' => true,
            'url_id' => $url->id,
        ]);
    }

    public function test_creating_static_qr_creates_no_backing_url(): void
    {
        [$user, $project] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hello']]),
            ['X-Inertia' => 'true'],
        )->assertRedirect(route('app.project.qrcodes.index'));

        $this->assertDatabaseCount('urls', 0);
        $this->assertDatabaseHas('qr_codes', ['name' => 'My QR', 'is_dynamic' => false, 'url_id' => null]);
    }

    public function test_updating_dynamic_qr_updates_its_backing_url(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo']),
            ['X-Inertia' => 'true'],
        );

        $qr = QrCode::firstOrFail();

        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            $this->payload([
                'domain_id' => $domain->id,
                'slug' => 'promo-2',
                'content' => ['url' => 'https://example.com/changed'],
            ]),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $this->assertDatabaseHas('urls', [
            'id' => $qr->fresh()->url_id,
            'slug' => 'promo-2',
            'url' => 'https://example.com/changed',
        ]);
        // Still exactly one backing link — updated in place, not duplicated.
        $this->assertDatabaseCount('urls', 1);
    }

    public function test_switching_dynamic_to_static_removes_backing_url(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo']),
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();
        $urlId = $qr->url_id;

        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            $this->payload(['type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hi']]),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $this->assertNull($qr->fresh()->url_id);
        $this->assertSoftDeleted('urls', ['id' => $urlId]);
    }

    public function test_deleting_dynamic_qr_soft_deletes_its_backing_url(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo']),
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();
        $urlId = $qr->url_id;

        $this->actingAs($user)->delete(
            route('app.project.qrcodes.destroy', ['project' => $project->id, 'qrCode' => $qr->id]),
        )->assertRedirect(route('app.project.qrcodes.index'));

        $this->assertSoftDeleted('qr_codes', ['id' => $qr->id]);
        $this->assertSoftDeleted('urls', ['id' => $urlId]);
    }

    private function fakeGeo(): void
    {
        $this->app->instance(GeoIpService::class, new class extends GeoIpService
        {
            public function __construct() {}

            public function lookup(string $ip): array
            {
                return ['country' => 'Germany', 'city' => 'Berlin', 'country_code' => 'DE', 'subdivision_code' => 'BE'];
            }
        });
    }

    public function test_scanning_a_link_qr_records_a_statistic(): void
    {
        $this->fakeGeo();
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo']),
            ['X-Inertia' => 'true'],
        );
        $urlId = QrCode::firstOrFail()->url_id;

        // Scanning the QR == visiting its short link.
        $this->get('http://links.test/promo')->assertRedirect('https://example.com/landing');

        $this->assertDatabaseHas('statistics', [
            'url_id' => $urlId,
            'project_id' => $project->id,
            'country' => 'Germany',
        ]);
    }

    public function test_duplicate_slug_on_same_domain_is_rejected(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo']),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'promo']),
            ['X-Inertia' => 'true'],
        )->assertJsonValidationErrors(['slug']);
    }

    public function test_switching_static_to_dynamic_creates_backing_url(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hi']]),
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();
        $this->assertNull($qr->url_id);

        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            $this->payload(['domain_id' => $domain->id, 'slug' => 'now-dynamic']),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $this->assertNotNull($qr->fresh()->url_id);
        $this->assertDatabaseHas('urls', ['slug' => 'now-dynamic', 'url' => 'https://example.com/landing']);
    }

    public function test_vcard_backing_url_points_to_its_canonical_short_url(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload(['name' => 'Jane', 'type' => 'vcard', 'domain_id' => $domain->id, 'slug' => 'jane', 'content' => ['name' => 'Jane Doe']]),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        // vCard backing Url stores its own canonical short URL (never used as a 302 target).
        $this->assertDatabaseHas('urls', ['slug' => 'jane', 'url' => 'https://links.test/jane']);
    }

    public function test_scanning_a_vcard_qr_serves_a_vcf_and_records_a_scan(): void
    {
        $this->fakeGeo();
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            $this->payload([
                'name' => 'Jane',
                'type' => 'vcard',
                'domain_id' => $domain->id,
                'slug' => 'jane',
                'content' => ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+4912345'],
            ]),
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();
        $urlId = QrCode::firstOrFail()->url_id;

        $response = $this->get('http://links.test/jane');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/vcard; charset=utf-8');
        $this->assertStringContainsString('FN:Jane Doe', $response->getContent());
        $this->assertStringContainsString('EMAIL:jane@example.com', $response->getContent());

        $this->assertDatabaseHas('statistics', ['url_id' => $urlId, 'country' => 'Germany']);
    }
}
