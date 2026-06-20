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
}
