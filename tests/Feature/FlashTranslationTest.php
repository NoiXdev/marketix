<?php

namespace Tests\Feature;

use App\Enums\PixelProvider;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FlashTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_flash_messages_use_the_users_language(): void
    {
        $user = User::factory()->create(['locale' => 'de']);
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $pixel = $project->pixels()->create(['provider' => PixelProvider::cases()[0]->value, 'name' => 'P', 'tag' => 'TAG']);

        $this->actingAs($user)
            ->delete(route('app.project.pixels.destroy', ['project' => $project->id, 'pixel' => $pixel->id]))
            ->assertSessionHas('success', 'Pixel gelöscht.');
    }

    public function test_every_locale_defines_every_flash_message(): void
    {
        $expected = array_keys($this->flatten(require lang_path('en/flash.php')));

        foreach (File::directories(lang_path()) as $directory) {
            $keys = array_keys($this->flatten(require $directory.'/flash.php'));

            $this->assertEqualsCanonicalizing($expected, $keys, basename($directory));
        }
    }

    private function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];

        foreach ($messages as $key => $value) {
            is_array($value)
                ? $flat += $this->flatten($value, $prefix.$key.'.')
                : $flat[$prefix.$key] = $value;
        }

        return $flat;
    }
}
