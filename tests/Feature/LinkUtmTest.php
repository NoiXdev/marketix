<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkUtmTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Project, 2: Domain} */
    private function tenant(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $project->users()->attach($user->id, ['role' => 'member']);
        $domain = Domain::create(['project_id' => $project->id, 'name' => 'links.test']);

        return [$user, $project, $domain];
    }

    /** @param array<string, mixed> $attributes */
    private function makeUrl(Project $project, Domain $domain, array $attributes = []): Url
    {
        return Url::create(array_merge([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => User::factory()->create()->id,
            'slug' => 'promo',
            'url' => 'https://example.com/default',
            'type' => RedirectType::REDIRECT,
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ], $attributes));
    }

    public function test_creating_a_link_stores_normalized_utm(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->post(route('app.project.links.store', ['project' => $project->id]), [
            'domain_id' => $domain->id,
            'slug' => 'promo',
            'url' => 'https://example.com/landing',
            'type' => RedirectType::REDIRECT->value,
            'status' => UrlStatus::ACTIVATED->value,
            'utm' => ['source' => ' newsletter ', 'medium' => 'email', 'campaign' => '', 'term' => null],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['source' => 'newsletter', 'medium' => 'email'], Url::firstOrFail()->utm);
    }

    public function test_unknown_utm_keys_are_rejected(): void
    {
        [$user, $project, $domain] = $this->tenant();

        $this->actingAs($user)->postJson(route('app.project.links.store', ['project' => $project->id]), [
            'domain_id' => $domain->id,
            'slug' => 'promo',
            'url' => 'https://example.com/landing',
            'type' => RedirectType::REDIRECT->value,
            'status' => UrlStatus::ACTIVATED->value,
            'utm' => ['source' => 'newsletter', 'gclid' => 'abc'],
        ], ['X-Inertia' => 'true'])->assertJsonValidationErrors(['utm']);
    }

    public function test_redirect_appends_utm_to_the_default_target(): void
    {
        [, $project, $domain] = $this->tenant();
        $this->makeUrl($project, $domain, ['url' => 'https://example.com/landing?ref=1', 'utm' => ['source' => 'qr', 'campaign' => 'autumn']]);

        $this->get('http://links.test/promo')
            ->assertRedirect('https://example.com/landing?ref=1&utm_source=qr&utm_campaign=autumn');
    }

    public function test_redirect_tags_targeting_destinations_too(): void
    {
        [, $project, $domain] = $this->tenant();
        $this->makeUrl($project, $domain, [
            'targeting_language' => [['language' => 'de', 'url' => 'https://example.com/de']],
            'utm' => ['source' => 'qr'],
        ]);

        $this->withHeaders(['Accept-Language' => 'de-CH,de;q=0.9'])
            ->get('http://links.test/promo')
            ->assertRedirect('https://example.com/de?utm_source=qr');
    }

    public function test_link_without_utm_redirects_unchanged(): void
    {
        [, $project, $domain] = $this->tenant();
        $this->makeUrl($project, $domain);

        $this->get('http://links.test/promo')->assertRedirect('https://example.com/default');
    }
}
