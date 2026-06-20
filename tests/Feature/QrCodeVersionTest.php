<?php

namespace Tests\Feature;

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
}
