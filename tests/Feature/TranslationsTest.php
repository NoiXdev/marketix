<?php

namespace Tests\Feature;

use App\Enums\PixelProvider;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TranslationsTest extends TestCase
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

    public function test_login_errors_use_the_visitors_language(): void
    {
        User::factory()->create(['email' => 'jane@example.test']);

        $this->post(route('app.auth.login'), ['email' => 'jane@example.test', 'password' => 'wrong'], ['Accept-Language' => 'de'])
            ->assertSessionHasErrors(['email' => 'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.']);
    }

    public function test_every_locale_defines_every_translation(): void
    {
        foreach (File::files(lang_path('en')) as $file) {
            $expected = array_keys($this->flatten(require $file->getPathname()));

            foreach (File::directories(lang_path()) as $directory) {
                $path = $directory.'/'.$file->getFilename();
                $keys = File::exists($path) ? array_keys($this->flatten(require $path)) : [];

                $this->assertEqualsCanonicalizing($expected, $keys, basename($directory).'/'.$file->getFilename());
            }
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
