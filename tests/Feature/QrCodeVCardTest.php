<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeVCardTest extends TestCase
{
    use RefreshDatabase;

    private array $style = [
        'foreground' => '#000000', 'background' => '#ffffff',
        'dot_style' => 'square', 'corner_square_style' => 'square',
        'corner_dot_style' => 'square', 'logo_type' => 'none',
        'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
    ];

    public function test_vcard_string_appends_extras_before_end(): void
    {
        $qr = new QrCode(['content' => [
            'name' => 'Jane Doe', 'phone' => '+49 30 1', 'email' => '',
            'org' => '', 'url' => '', 'address' => '',
            'extra' => "TITLE:CTO\nBDAY:1990-01-01\nTEL;TYPE=HOME:+49 30 2",
        ]]);

        $out = $qr->vCardString();

        $this->assertStringContainsString("FN:Jane Doe", $out);
        $this->assertStringContainsString("TITLE:CTO", $out);
        $this->assertStringContainsString("TEL;TYPE=HOME:+49 30 2", $out);
        $this->assertStringEndsWith("END:VCARD", $out);
        // extras come after the mapped fields
        $this->assertGreaterThan(strpos($out, 'FN:'), strpos($out, 'TITLE:CTO'));
    }

    public function test_vcard_string_without_extras_is_unchanged(): void
    {
        $qr = new QrCode(['content' => [
            'name' => 'Jane', 'phone' => '', 'email' => '', 'org' => '', 'url' => '', 'address' => '',
        ]]);

        $this->assertSame("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Jane\r\nEND:VCARD", $qr->vCardString());
    }

    public function test_store_accepts_a_vcard_extra_string(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $this->actingAs($user)->postJson(
            route('app.project.qrcodes.store', ['project' => $project->id]),
            [
                'name' => 'Card', 'type' => 'vcard', 'is_dynamic' => false,
                'content' => ['name' => 'Jane', 'extra' => "TITLE:CTO"],
                'style' => $this->style,
            ],
            ['X-Inertia' => 'true'],
        )->assertSessionHasNoErrors();

        $qr = QrCode::firstOrFail();
        $this->assertSame('TITLE:CTO', $qr->content['extra']);
    }
}
