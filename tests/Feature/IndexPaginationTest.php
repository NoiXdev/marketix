<?php

namespace Tests\Feature;

use App\Enums\PixelProvider;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Url;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class IndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'Acme']);
        $this->user->projects()->attach($this->project);
        $this->domain = Domain::create(['project_id' => $this->project->id, 'name' => 'links.test']);
    }

    private function links(int $count): void
    {
        Url::factory()
            ->count($count)
            ->state(new Sequence(fn (Sequence $sequence) => ['slug' => 'link-'.$sequence->index]))
            ->create(['project_id' => $this->project->id, 'domain_id' => $this->domain->id, 'url' => 'https://example.com']);
    }

    public function test_links_are_paginated_by_25_by_default(): void
    {
        $this->links(30);

        $this->actingAs($this->user)
            ->get(route('app.project.links.index', ['project' => $this->project->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('urls.data', 25)
                ->where('urls.total', 30)
                ->where('urls.last_page', 2)
                ->where('filters', ['search' => '', 'per_page' => 25])
            );
    }

    public function test_the_page_size_can_be_raised_but_only_to_allowed_values(): void
    {
        $this->links(30);

        $this->actingAs($this->user)
            ->get(route('app.project.links.index', ['project' => $this->project->id, 'per_page' => 50]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('urls.data', 30)->where('filters.per_page', 50));

        $this->actingAs($this->user)
            ->get(route('app.project.links.index', ['project' => $this->project->id, 'per_page' => 1000]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('urls.data', 25)->where('filters.per_page', 25));
    }

    public function test_links_can_be_searched_by_slug_target_and_domain(): void
    {
        $other = Domain::create(['project_id' => $this->project->id, 'name' => 'promo.test']);
        Url::factory()->create(['project_id' => $this->project->id, 'domain_id' => $this->domain->id, 'slug' => 'summer-sale', 'url' => 'https://example.com']);
        Url::factory()->create(['project_id' => $this->project->id, 'domain_id' => $this->domain->id, 'slug' => 'docs', 'url' => 'https://shop.example.com/sale']);
        Url::factory()->create(['project_id' => $this->project->id, 'domain_id' => $other->id, 'slug' => 'other', 'url' => 'https://example.com']);
        Url::factory()->create(['project_id' => $this->project->id, 'domain_id' => $this->domain->id, 'slug' => 'unrelated', 'url' => 'https://example.com']);

        $search = fn (string $term) => $this->actingAs($this->user)
            ->get(route('app.project.links.index', ['project' => $this->project->id, 'search' => $term]));

        $search('sale')->assertInertia(fn (AssertableInertia $page) => $page->where('urls.total', 2)->where('filters.search', 'sale'));
        $search('promo.test')->assertInertia(fn (AssertableInertia $page) => $page->where('urls.total', 1)->where('urls.data.0.slug', 'other'));
    }

    public function test_qr_codes_domains_and_pixels_can_be_searched(): void
    {
        foreach (['Flyer', 'Poster'] as $name) {
            $this->project->qrCodes()->create(['name' => $name, 'type' => 'text', 'is_dynamic' => false, 'content' => ['text' => 'hi'], 'style' => []]);
            $this->project->pixels()->create(['provider' => PixelProvider::cases()[0]->value, 'name' => $name, 'tag' => 'TAG-'.$name]);
        }
        Domain::create(['project_id' => $this->project->id, 'name' => 'shop.test']);

        $this->actingAs($this->user)
            ->get(route('app.project.qrcodes.index', ['project' => $this->project->id, 'search' => 'fly']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('qrCodes.total', 1)->where('qrCodes.data.0.name', 'Flyer'));

        $this->actingAs($this->user)
            ->get(route('app.project.pixels.index', ['project' => $this->project->id, 'search' => 'TAG-Poster']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pixels.total', 1)->where('pixels.data.0.name', 'Poster'));

        $this->actingAs($this->user)
            ->get(route('app.project.domains.index', ['project' => $this->project->id, 'search' => 'shop']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('domains.total', 1)->where('domains.data.0.name', 'shop.test'));
    }
}
