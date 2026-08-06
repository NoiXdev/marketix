<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrStyleMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Migration files are anonymous classes (`return new class extends
     * Migration {...}`); `include`-ing the file hands back that instance
     * so we can invoke up() directly and deterministically, without going
     * through Artisan's migrator.
     */
    private function migration(): object
    {
        return include database_path('migrations/2026_08_06_161028_migrate_qr_styles_to_v2.php');
    }

    public function test_migration_rewrites_old_shape_style_on_qr_code_and_version(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);

        $oldStyle = [
            'foreground' => '#111111',
            'background' => '#eeeeee',
            'dot_style' => 'extra-rounded',
            'corner_square_style' => 'extra-rounded',
            'corner_dot_style' => 'dot',
            'logo_type' => 'none',
            'logo_name' => '',
            'logo_data' => '',
            'logo_size' => 30,
        ];

        $qr = $project->qrCodes()->create([
            'name' => 'Old style QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => $oldStyle,
        ]);

        $version = $qr->versions()->create([
            'version' => 1, 'name' => 'Old style QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => $oldStyle, 'created_by' => $user->id,
        ]);

        $this->migration()->up();

        foreach ([$qr->fresh(), $version->fresh()] as $row) {
            $style = $row->style;

            // extra-rounded / extra-rounded / dot mapping.
            $this->assertSame('rounded', $style['module_mode']);
            $this->assertEquals(1.0, $style['module_rounding']);
            $this->assertSame('rounded', $style['eye_frame_mode']);
            $this->assertEquals(1.0, $style['eye_frame_rounding']);
            $this->assertSame('dot', $style['eye_ball_mode']);
            $this->assertEquals(1.0, $style['eye_ball_rounding']);

            $this->assertSame('Q', $style['error_correction']);
            $this->assertSame(4, $style['quiet_zone']);
            $this->assertSame(1, $style['logo_margin']);
            $this->assertTrue($style['logo_clear_modules']);
            $this->assertSame('none', $style['frame_style']);
            $this->assertSame('', $style['frame_text']);
            $this->assertSame('#000000', $style['frame_text_color']);
            $this->assertSame('#000000', $style['frame_color']);
            $this->assertSame('#ffffff', $style['frame_background']);

            // Carried over unchanged.
            $this->assertSame('#111111', $style['foreground']);
            $this->assertSame('#eeeeee', $style['background']);
            $this->assertSame('none', $style['logo_type']);
            $this->assertSame(30, $style['logo_size']);

            // Old keys retained (harmless; Task 11 removes them logically).
            $this->assertSame('extra-rounded', $style['dot_style']);
            $this->assertSame('extra-rounded', $style['corner_square_style']);
            $this->assertSame('dot', $style['corner_dot_style']);
        }
    }

    public function test_migration_is_idempotent(): void
    {
        $project = Project::create(['name' => 'Acme']);

        $qr = $project->qrCodes()->create([
            'name' => 'Old style QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'],
            'style' => [
                'foreground' => '#000000', 'background' => '#ffffff',
                'dot_style' => 'square', 'corner_square_style' => 'square',
                'corner_dot_style' => 'square', 'logo_type' => 'none',
                'logo_name' => '', 'logo_data' => '', 'logo_size' => 30,
            ],
        ]);

        $migration = $this->migration();
        $migration->up();

        $afterFirstRun = $qr->fresh()->style;
        $this->assertSame('square', $afterFirstRun['module_mode']);

        // Running it again must leave already-migrated rows byte-for-byte
        // unchanged (detected via presence of module_mode).
        $migration->up();
        $this->assertSame($afterFirstRun, $qr->fresh()->style);
    }

    public function test_migration_skips_rows_already_in_the_new_shape(): void
    {
        $project = Project::create(['name' => 'Acme']);

        $newStyle = [
            'foreground' => '#000000', 'background' => '#ffffff',
            'module_mode' => 'dots', 'module_rounding' => 1,
            'eye_frame_mode' => 'square', 'eye_frame_rounding' => 0,
            'eye_ball_mode' => 'square', 'eye_ball_rounding' => 0,
            'error_correction' => 'H', 'quiet_zone' => 2,
            'logo_margin' => 0, 'logo_clear_modules' => false,
            'frame_style' => 'simple', 'frame_text' => 'Scan me',
            'frame_text_color' => '#111111', 'frame_color' => '#222222',
            'frame_background' => '#333333',
        ];

        $qr = $project->qrCodes()->create([
            'name' => 'New style QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => $newStyle,
        ]);

        $this->migration()->up();

        $this->assertSame($newStyle, $qr->fresh()->style);
    }
}
