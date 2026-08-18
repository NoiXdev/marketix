<?php

namespace Tests\Feature\Demo;

use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertNotSame('', app(\App\Settings\MailSettings::class)->from_address);
        $this->assertNotNull(app(\App\Settings\BrandingSettings::class)->appName());
        $this->assertNotSame('', app(\App\Settings\StorageSettings::class)->driver);
    }
}
