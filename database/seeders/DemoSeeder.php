<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Builds the demo instance's data: one fictional company, populated so that
 * every menu item looks alive. All timestamps are relative to now() so the
 * dashboards never look stale, no matter when the last reset ran.
 *
 * Content is deliberately mixed German/English, mirroring a real customer's
 * link inventory.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public const COMPANY = 'Nordlicht Kaffeerösterei';

    private User $demoUser;

    private Project $project;

    private Domain $domain;

    public function run(): void
    {
        $this->seedUsersAndProject();
        $this->seedDomain();
    }

    private function seedUsersAndProject(): void
    {
        $this->demoUser = User::create([
            'name' => 'Demo',
            'email' => config('demo.email'),
            'password' => config('demo.password'),
            'locale' => 'de',
        ]);

        // super_admin is intentionally not mass-assignable.
        $this->demoUser->super_admin = true;
        $this->demoUser->save();

        $this->project = Project::create(['name' => self::COMPANY, 'locked' => false]);
        $this->project->users()->attach($this->demoUser, ['role' => 'admin', 'active' => true]);

        $teammates = [
            ['name' => 'Lena Brandt', 'email' => 'lena@nordlicht.example', 'role' => 'admin', 'locale' => 'de'],
            ['name' => 'Tom Feldkamp', 'email' => 'tom@nordlicht.example', 'role' => 'member', 'locale' => 'de'],
            ['name' => 'Priya Raman', 'email' => 'priya@nordlicht.example', 'role' => 'member', 'locale' => 'en'],
        ];

        foreach ($teammates as $mate) {
            $user = User::create([
                'name' => $mate['name'],
                'email' => $mate['email'],
                'password' => bin2hex(random_bytes(16)),
                'locale' => $mate['locale'],
            ]);

            $this->project->users()->attach($user, ['role' => $mate['role'], 'active' => true]);
        }
    }

    private function seedDomain(): void
    {
        $this->domain = Domain::create([
            'project_id' => $this->project->id,
            'name' => 'nord.'.config('app.domain'),
            'redirect_root' => 'https://example.com/',
            'redirect_not_found' => 'https://example.com/404',
        ]);
    }
}
