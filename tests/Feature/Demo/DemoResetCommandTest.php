<?php

namespace Tests\Feature\Demo;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class DemoResetCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_refuses_to_run_outside_demo_mode(): void
    {
        config(['demo.enabled' => false]);

        $this->artisan('marketix:demo:reset')->assertExitCode(1);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_it_wipes_and_reseeds_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        // Pre-existing junk a visitor might have left behind.
        Project::create(['name' => 'Junk left by a visitor', 'locked' => false]);

        $this->artisan('marketix:demo:reset')->assertExitCode(0);

        $this->assertDatabaseMissing('projects', ['name' => 'Junk left by a visitor']);
        $this->assertDatabaseHas('projects', ['name' => DemoSeeder::COMPANY]);
        $this->assertNotNull(User::query()->where('email', config('demo.email'))->first());
    }
}
