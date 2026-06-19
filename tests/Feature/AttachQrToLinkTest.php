<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Enums\UrlStatus;
use App\Models\Domain;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AttachQrToLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Project, 2: Domain}
     */
    private function makeProject(): array
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Acme']);
        $user->projects()->attach($project);
        $domain = Domain::firstOrCreate(['project_id' => $project->id, 'name' => 'links.test']);

        return [$user, $project, $domain];
    }

    private function makeLink(Project $project, Domain $domain, User $user, string $slug = 'promo'): Url
    {
        return Url::create([
            'project_id' => $project->id,
            'domain_id' => $domain->id,
            'user_id' => $user->id,
            'slug' => $slug,
            'url' => 'https://example.com/landing',
            'type' => RedirectType::cases()[0],
            'status' => UrlStatus::ACTIVATED,
            'archived' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function style(): array
    {
        return [
            'foreground' => '#000000',
            'background' => '#ffffff',
            'dot_style' => 'square',
            'corner_square_style' => 'square',
            'corner_dot_style' => 'square',
            'logo_type' => 'none',
            'logo_name' => '',
            'logo_data' => '',
            'logo_size' => 30,
        ];
    }

    public function test_create_page_prefills_from_the_link(): void
    {
        [$user, $project, $domain] = $this->makeProject();
        $link = $this->makeLink($project, $domain, $user);

        $this->actingAs($user)
            ->get(route('app.project.qrcodes.create', ['project' => $project->id, 'link' => $link->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('QrCodes/Create')
                ->where('attachUrl.id', $link->id)
                ->where('attachUrl.slug', 'promo')
                ->where('attachUrl.domain_id', $domain->id)
                ->where('attachUrl.domain_name', 'links.test')
                ->where('attachUrl.target', 'https://example.com/landing')
            );
    }

    public function test_create_page_has_null_attach_url_without_param(): void
    {
        [$user, $project] = $this->makeProject();

        $this->actingAs($user)
            ->get(route('app.project.qrcodes.create', ['project' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('attachUrl', null));
    }

    public function test_create_redirects_when_the_link_already_has_a_qr(): void
    {
        [$user, $project, $domain] = $this->makeProject();
        $link = $this->makeLink($project, $domain, $user);
        $qr = $project->qrCodes()->create([
            'url_id' => $link->id,
            'name' => 'Existing',
            'type' => 'link',
            'is_dynamic' => true,
            'content' => ['url' => 'https://example.com/landing'],
            'style' => $this->style(),
        ]);

        $this->actingAs($user)
            ->get(route('app.project.qrcodes.create', ['project' => $project->id, 'link' => $link->id]))
            ->assertRedirect(route('app.project.qrcodes.edit', ['project' => $project->id, 'qrCode' => $qr->id]));
    }

    public function test_create_404s_for_a_foreign_link(): void
    {
        [$user, $project] = $this->makeProject();
        [$other, $otherProject, $otherDomain] = $this->makeProject();
        $foreign = $this->makeLink($otherProject, $otherDomain, $other, 'theirs');

        $this->actingAs($user)
            ->get(route('app.project.qrcodes.create', ['project' => $project->id, 'link' => $foreign->id]))
            ->assertNotFound();
    }

    public function test_store_in_attach_mode_reuses_the_existing_link(): void
    {
        [$user, $project, $domain] = $this->makeProject();
        $link = $this->makeLink($project, $domain, $user);

        $this->actingAs($user)
            ->post(route('app.project.qrcodes.store', ['project' => $project->id]), [
                'url_id' => $link->id,
                'name' => 'Promo QR',
                'type' => 'link',
                'is_dynamic' => true,
                'domain_id' => $domain->id,
                'slug' => 'promo',
                'content' => ['url' => 'https://example.com/landing'],
                'style' => $this->style(),
            ])
            ->assertRedirect();

        $this->assertSame(1, Url::count());
        $this->assertDatabaseHas('qr_codes', ['url_id' => $link->id, 'name' => 'Promo QR']);
        $link->refresh();
        $this->assertSame('promo', $link->slug);
        $this->assertSame('https://example.com/landing', $link->url);
        $this->assertNotNull($link->qrCode);
    }

    public function test_store_rejects_attaching_to_a_link_that_already_has_a_qr(): void
    {
        [$user, $project, $domain] = $this->makeProject();
        $link = $this->makeLink($project, $domain, $user);
        $project->qrCodes()->create([
            'url_id' => $link->id,
            'name' => 'Existing',
            'type' => 'link',
            'is_dynamic' => true,
            'content' => ['url' => 'https://example.com/landing'],
            'style' => $this->style(),
        ]);

        $this->actingAs($user)
            ->post(route('app.project.qrcodes.store', ['project' => $project->id]), [
                'url_id' => $link->id,
                'name' => 'Dupe QR',
                'type' => 'link',
                'is_dynamic' => true,
                'domain_id' => $domain->id,
                'slug' => 'promo',
                'content' => ['url' => 'https://example.com/landing'],
                'style' => $this->style(),
            ])
            ->assertSessionHasErrors('url_id');

        $this->assertSame(1, QrCode::count());
    }

    public function test_store_rejects_a_foreign_url_id(): void
    {
        [$user, $project, $domain] = $this->makeProject();
        [$other, $otherProject, $otherDomain] = $this->makeProject();
        $foreign = $this->makeLink($otherProject, $otherDomain, $other, 'theirs');

        $this->actingAs($user)
            ->post(route('app.project.qrcodes.store', ['project' => $project->id]), [
                'url_id' => $foreign->id,
                'name' => 'Sneaky QR',
                'type' => 'link',
                'is_dynamic' => true,
                'domain_id' => $domain->id,
                'slug' => 'sneaky',
                'content' => ['url' => 'https://example.com/landing'],
                'style' => $this->style(),
            ])
            ->assertSessionHasErrors('url_id');
    }

    public function test_store_without_url_id_still_creates_a_new_backing_link(): void
    {
        [$user, $project, $domain] = $this->makeProject();

        $this->actingAs($user)
            ->post(route('app.project.qrcodes.store', ['project' => $project->id]), [
                'name' => 'Fresh QR',
                'type' => 'link',
                'is_dynamic' => true,
                'domain_id' => $domain->id,
                'slug' => 'fresh',
                'content' => ['url' => 'https://example.com/x'],
                'style' => $this->style(),
            ])
            ->assertRedirect();

        $this->assertSame(1, Url::count());
        $this->assertDatabaseHas('urls', ['slug' => 'fresh', 'domain_id' => $domain->id]);
    }
}
