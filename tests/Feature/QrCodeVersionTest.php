<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrCodeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_code_has_many_versions(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $qr = $project->qrCodes()->create([
            'name' => 'My QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
        ]);

        $version = $qr->versions()->create([
            'version' => 1, 'name' => 'My QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
            'created_by' => $user->id,
        ]);

        $this->assertTrue($qr->versions->contains($version));
        $this->assertSame($user->id, $version->creator->id);
        $this->assertIsArray($version->content);
    }

    public function test_creating_a_qr_records_version_one(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            [
                'name' => 'My QR', 'type' => 'text', 'is_dynamic' => false,
                'content' => ['text' => 'hi'],
                'style' => [
                    'foreground' => '#000000', 'background' => '#ffffff',
                    'dot_style' => 'square', 'corner_square_style' => 'square',
                    'corner_dot_style' => 'square', 'logo_type' => 'none',
                    'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
                ],
            ],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $qr = QrCode::firstOrFail();
        $this->assertDatabaseHas('qr_code_versions', [
            'qr_code_id' => $qr->id, 'version' => 1, 'name' => 'My QR', 'created_by' => $user->id,
        ]);
        $this->assertSame(1, $qr->versions()->count());
    }

    public function test_updating_a_qr_appends_a_version(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'My QR', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hi'], 'style' => $style],
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();

        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['name' => 'Renamed', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'bye'], 'style' => $style],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $this->assertSame(2, $qr->versions()->count());
        $this->assertDatabaseHas('qr_code_versions', ['qr_code_id' => $qr->id, 'version' => 2, 'name' => 'Renamed']);
    }

    public function test_restoring_a_version_applies_its_snapshot_and_appends_a_version(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'Original', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'first'], 'style' => $style],
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();

        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['name' => 'Changed', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'second'], 'style' => $style],
            ['X-Inertia' => 'true'],
        );

        // Restore v1.
        $this->actingAs($user)->post(
            route('app.project.qrcodes.versions.restore', ['project' => $project->id, 'qrCode' => $qr->id, 'version' => 1]),
        )->assertRedirect(route('app.project.qrcodes.index'));

        $fresh = $qr->fresh();
        $this->assertSame('Original', $fresh->name);
        $this->assertSame(['text' => 'first'], $fresh->content);
        // Restore is non-destructive: v1, v2 (update), v3 (restore).
        $this->assertSame(3, $fresh->versions()->count());
    }

    public function test_restoring_a_dynamic_version_recreates_the_backing_link(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];

        // v1: dynamic link.
        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'Dyn', 'type' => 'link', 'is_dynamic' => true, 'domain_id' => $domain->id, 'slug' => 'promo', 'content' => ['url' => 'https://example.com/a'], 'style' => $style],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();
        $qr = QrCode::firstOrFail();

        // v2: switch to static.
        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['name' => 'Dyn', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'x'], 'style' => $style],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();
        $this->assertNull($qr->fresh()->url_id);

        // Restore v1 (dynamic) — backing link must come back.
        $this->actingAs($user)->post(
            route('app.project.qrcodes.versions.restore', ['project' => $project->id, 'qrCode' => $qr->id, 'version' => 1]),
        )->assertSessionHasNoErrors();

        $this->assertNotNull($qr->fresh()->url_id);
        $this->assertDatabaseHas('urls', ['slug' => 'promo', 'url' => 'https://example.com/a']);
    }

    public function test_edit_page_exposes_versions(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];
        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'My QR', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hi'], 'style' => $style],
            ['X-Inertia' => 'true'],
        );
        $qr = QrCode::firstOrFail();

        $version = file_exists($manifest = public_path('build/manifest.json'))
            ? hash_file('xxh128', $manifest)
            : '';

        $this->actingAs($user)->get(
            route('app.project.qrcodes.edit', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'versions', 'X-Inertia-Partial-Component' => 'QrCodes/Edit', 'X-Inertia-Version' => $version],
        )->assertOk()->assertJsonPath('props.versions.0.version', 1);
    }

    public function test_dynamic_to_static_version_has_null_domain_and_slug(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        $style = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'dot_style' => 'square', 'corner_square_style' => 'square',
            'corner_dot_style' => 'square', 'logo_type' => 'none',
            'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
        ];

        // v1: dynamic QR — version row must record domain_id and slug.
        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            ['name' => 'Dyn', 'type' => 'link', 'is_dynamic' => true, 'domain_id' => $domain->id, 'slug' => 'abc123', 'content' => ['url' => 'https://example.com/a'], 'style' => $style],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $qr = QrCode::firstOrFail();
        $this->assertDatabaseHas('qr_code_versions', [
            'qr_code_id' => $qr->id,
            'version' => 1,
            'is_dynamic' => true,
            'domain_id' => $domain->id,
            'slug' => 'abc123',
        ]);

        // v2: switch to static — version row MUST have null domain_id and null slug.
        $this->actingAs($user)->putJson(
            route('app.project.qrcodes.update', ['project' => $project->id, 'qrCode' => $qr->id]),
            ['name' => 'Dyn', 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hello'], 'style' => $style],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $this->assertDatabaseHas('qr_code_versions', [
            'qr_code_id' => $qr->id,
            'version' => 2,
            'is_dynamic' => false,
            'domain_id' => null,
            'slug' => null,
        ]);
    }

    public function test_cannot_restore_another_projects_qr_version(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $other = Project::create(['name' => 'Other']);
        $otherUser = User::factory()->create();
        $other->users()->attach($otherUser->id, ['role' => 'member']);
        $otherQr = $other->qrCodes()->create([
            'name' => 'Theirs', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
        ]);
        $otherQr->versions()->create([
            'version' => 1, 'name' => 'Theirs', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
        ]);

        // $user (Acme) trying to restore Other's QR → 404 (scoped via project).
        $this->actingAs($user)->post(
            route('app.project.qrcodes.versions.restore', ['project' => $project->id, 'qrCode' => $otherQr->id, 'version' => 1]),
        )->assertNotFound();
    }
}
