<?php

namespace Tests\Feature\Demo;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // The `app.demo.login` route is registered conditionally at boot
        // (`if (config('demo.enabled'))` in routes/web.php), so flipping
        // config after boot is too late. Flip the env before the
        // application boots instead.
        putenv('DEMO_MODE=true');
        $_ENV['DEMO_MODE'] = 'true';
        $_SERVER['DEMO_MODE'] = 'true';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('DEMO_MODE');
        unset($_ENV['DEMO_MODE'], $_SERVER['DEMO_MODE']);

        parent::tearDown();
    }

    private function seedDemoAccount(): User
    {
        $user = User::factory()->create(['email' => config('demo.email')]);
        $project = Project::create(['name' => 'Demo', 'locked' => false]);
        $project->users()->attach($user, ['role' => 'admin', 'active' => true]);

        return $user;
    }

    public function test_one_click_login_authenticates_the_demo_account(): void
    {
        $this->assertNotNull(app('router')->getRoutes()->getByName('app.demo.login'));

        $user = $this->seedDemoAccount();

        $this->post(route('app.demo.login'))->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_refused_when_the_demo_account_is_missing(): void
    {
        $this->post(route('app.demo.login'))->assertNotFound();

        $this->assertGuest();
    }
}
