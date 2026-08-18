<?php

namespace Tests\Feature\Demo;

use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use App\Settings\MailSettings;
use App\Settings\StorageSettings;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_demo_account_and_project(): void
    {
        $this->seed(DemoSeeder::class);

        $demo = User::query()->where('email', config('demo.email'))->first();
        $this->assertNotNull($demo);
        $this->assertTrue($demo->super_admin);

        $project = Project::query()->where('name', DemoSeeder::COMPANY)->first();
        $this->assertNotNull($project);
        $this->assertSame(4, $project->users()->count());
        $this->assertSame(1, Domain::query()->where('project_id', $project->id)->count());
    }

    public function test_the_settings_groups_survive_a_fresh_migrate(): void
    {
        // The settings migrations in database/settings/ re-create and seed
        // every property, so a reset never leaves the instance unconfigured.
        $this->assertNotSame('', app(MailSettings::class)->from_address);
        $this->assertNotSame('', app(StorageSettings::class)->driver);

        // BrandingSettings::appName() falls back to 'Marketix' whenever
        // app_name is null — which is also what spatie/laravel-settings
        // substitutes for a property that was never migrated at all, so
        // assertNotNull(appName()) can't tell "seeded" apart from "never
        // existed" (see app/Settings/BrandingSettings.php:27). Assert
        // directly against the settings table instead: the branding
        // migration adds exactly 5 rows (database/settings/2026_06_19_000000_create_branding_settings.php),
        // so their presence is proof the migration actually ran on this reset.
        $this->assertSame(
            5,
            DB::table('settings')->where('group', 'branding')->count(),
        );
    }
}
