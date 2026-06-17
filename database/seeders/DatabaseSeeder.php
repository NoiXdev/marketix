<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        foreach (['A', 'B'] as $i) {
            $project = Project::create([
                'name' => "Project {$i}",
                'locked' => false,
            ]);

            $project->users()->attach($user, ['role' => 'admin', 'active' => true]);
        }
    }
}
