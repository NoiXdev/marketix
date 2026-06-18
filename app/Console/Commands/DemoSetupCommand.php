<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Console\Command;

class DemoSetupCommand extends Command
{
    protected $signature = 'demo:setup {--force}';

    protected $description = 'Command description';

    public function handle(): void
    {
        if (! $this->option('force')) {
            $sureQuestionMark = $this->ask('Are you sure you want to do this?', 'yes');
            if ($sureQuestionMark !== 'yes') {
                return;
            }
        }

        $this->call('migrate:fresh', ['--force' => true]);

        $this->generateUsers();
        $this->generateProjects();
        $this->generateDomains();
        $this->generateUrls();
    }

    private function generateUsers(): void
    {
        /** @var User[] $users */
        $users = [
            ['name' => 'admin', 'password' => 'password', 'email' => 'admin@example.com', 'super_admin' => true],
            ['name' => 'John Doe', 'password' => 'password', 'email' => 'john.doe@example.com'],
            ['name' => 'Max Mustermann', 'password' => 'password', 'email' => 'max.mustermann@example.com'],
        ];

        collect($users)->each(function ($user) {
            $u = User::create($user);

            // super_admin is intentionally not mass-assignable; set it explicitly.
            if (! empty($user['super_admin'])) {
                $u->super_admin = true;
                $u->save();
            }
        });
    }

    private function generateProjects(): void
    {
        $projects = [
            [
                'name' => 'Project 1', 'locked' => false, 'users' => [
                    ['user' => $this->getOwnerOneUser(), 'role' => 'admin', 'active' => true],
                ],
            ],
            [
                'name' => 'Project 2', 'locked' => false, 'users' => [
                    ['user' => $this->getOwnerTwoUser(), 'role' => 'admin', 'active' => true],
                    ['user' => $this->getOwnerOneUser(), 'role' => 'member', 'active' => true],
                ],
            ],
        ];

        collect($projects)->each(function ($project) {
            $p = Project::create($project);

            collect($project['users'])->each(function ($user) use ($p) {
                $p->users()->attach($user['user'], ['role' => $user['role'], 'active' => $user['active']]);
            });
        });
    }

    private function generateDomains(): void
    {
        $domains = [
            ['project_id' => $this->projectByName('Project 1')->id, 'name' => $this->isDDEV() ? 'project-a-marketix.ddev.site' : 'project-a.example.com', 'redirect_root' => 'https://google.com/search?q=Root', 'redirect_not_found' => 'https://google.com/search?q=NotFound'],
            ['project_id' => $this->projectByName('Project 2')->id, 'name' => $this->isDDEV() ? 'project-b-marketix.ddev.site' : 'project-b.example.com', 'redirect_root' => 'https://google.com/search?q=Root', 'redirect_not_found' => 'https://google.com/search?q=NotFound'],
        ];

        collect($domains)->each(function ($domain) {
            Domain::create($domain);
        });
    }

    private function generateUrls(): void
    {
        $projectA = $this->projectByName('Project 1');
        $projectB = $this->projectByName('Project 2');

        Url::factory(20)->named($projectA->id, $projectA->domains()->value('id'))->create();
        Url::factory(20)->named($projectB->id, $projectB->domains()->value('id'))->create();
    }

    /**
     * Helpers
     */
    private function isDDEV(): bool
    {
        return (bool) env('IS_DDEV_PROJECT');
    }

    private function projectByName(string $name): Project
    {
        return Project::where('name', $name)->firstOrFail();
    }

    private function getOwnerOneUser(): User
    {
        return User::whereEmail('john.doe@example.com')->first();
    }

    private function getOwnerTwoUser(): User
    {
        return User::whereEmail('max.mustermann@example.com')->first();
    }
}
