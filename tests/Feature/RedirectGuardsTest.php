<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RedirectGuardsTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function makeUrl(array $attributes = []): Url
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return Url::create(array_merge([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ], $attributes));
    }

    public function test_expired_link_returns_410(): void
    {
        $this->makeUrl(['expired_at' => now()->subDay()]);

        $this->get('http://links.test/promo')->assertStatus(410);
    }

    public function test_deactivated_link_returns_404(): void
    {
        $this->makeUrl(['status' => UrlStatus::DEACTIVATED]);

        $this->get('http://links.test/promo')->assertStatus(404);
    }

    public function test_archived_link_returns_404(): void
    {
        $this->makeUrl(['archived' => true]);

        $this->get('http://links.test/promo')->assertStatus(404);
    }

    public function test_password_protected_link_gates_and_hides_target(): void
    {
        $this->makeUrl(['password' => Hash::make('secret')]);

        $response = $this->get('http://links.test/promo');

        $response->assertOk(); // shows the password gate view, not a redirect
        $response->assertDontSee('https://example.com/default', false);
    }

    public function test_correct_password_allows_through(): void
    {
        $this->makeUrl(['password' => Hash::make('secret')]);

        $this->post('http://links.test/promo', ['password' => 'secret'])
            ->assertRedirect('https://example.com/default');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->makeUrl(['password' => Hash::make('secret')]);

        $this->post('http://links.test/promo', ['password' => 'nope'])
            ->assertSessionHasErrors('password');
    }
}
