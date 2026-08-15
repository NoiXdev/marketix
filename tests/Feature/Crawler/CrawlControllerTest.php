<?php

namespace Tests\Feature\Crawler;

use App\Jobs\RunCrawlJob;
use App\Models\Crawl;
use App\Models\CrawlLink;
use App\Models\CrawlPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CrawlControllerTest extends TestCase
{
    use RefreshDatabase;

    private function member(): array
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);

        return [$user, $project];
    }

    public function test_store_creates_crawl_and_dispatches_job(): void
    {
        Queue::fake();
        [$user, $project] = $this->member();

        $this->actingAs($user)
            ->post(route('app.project.crawls.store', ['project' => $project->id]), [
                'start_url' => 'https://example.com',
                'mode' => 'full_site',
                'render_js' => false,
                'respect_robots' => true,
                'include_subdomains' => false,
                'delay_ms' => 0,
            ])
            ->assertRedirect();

        $crawl = Crawl::first();
        $this->assertNotNull($crawl);
        $this->assertSame($project->id, $crawl->project_id);
        Queue::assertPushed(RunCrawlJob::class);
    }

    public function test_store_rejects_private_url(): void
    {
        Queue::fake();
        [$user, $project] = $this->member();

        $this->actingAs($user)
            ->post(route('app.project.crawls.store', ['project' => $project->id]), [
                'start_url' => 'http://127.0.0.1',
                'mode' => 'full_site',
                'delay_ms' => 0,
            ])
            ->assertSessionHasErrors('start_url');

        Queue::assertNotPushed(RunCrawlJob::class);
    }

    public function test_export_sanitizes_formula_injection_in_csv_cells(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create([
            'url' => 'https://example.com/page',
            'title' => '=cmd()',
            'issues' => ['=HYPERLINK("http://evil.test")'],
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.project.crawls.export', ['project' => $project->id, 'crawl' => $crawl->id]));

        $response->assertOk();
        $content = $response->streamedContent();

        $rows = array_map('str_getcsv', array_filter(explode("\n", $content)));
        $dataRow = $rows[1];

        // Columns: url, status_code, type, size_bytes, title, is_indexable, inlinks_count, depth, issues
        [$url, , , , $title, , , , $issues] = $dataRow;

        $this->assertStringStartsNotWith('=', $title);
        $this->assertSame("'=cmd()", $title);
        $this->assertStringStartsNotWith('=', $issues);
        $this->assertStringStartsWith("'", $issues);
        $this->assertStringStartsNotWith('=', $url);
    }

    public function test_cannot_view_another_projects_crawl(): void
    {
        [$user, $project] = $this->member();
        $other = Project::factory()->create();
        $crawl = Crawl::factory()->for($other)->create();

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertNotFound();
    }

    public function test_show_exposes_categories_and_filters_by_type(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create(['start_url' => 'https://example.com']);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/', 'content_category' => 'html']);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/logo.svg', 'content_category' => 'image']);

        // Unfiltered: both categories are offered and both pages are listed.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Crawls/Show')
                ->where('categories', ['html', 'image'])
                ->has('pages.data', 2)
            );

        // Filtered by image: only the SVG remains.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'category' => 'image']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.category', 'image')
                ->has('pages.data', 1)
                ->where('pages.data.0.content_category', 'image')
            );
    }

    public function test_show_filters_pages_by_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create(['start_url' => 'https://example.com']);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/a', 'issues' => ['missing_title', 'thin_content']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/b', 'issues' => ['broken_link']]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'issue' => 'missing_title']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Crawls/Show')
                ->where('filters.issue', 'missing_title')
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/a')
            );
    }

    public function test_show_groups_catalogue_and_filters_by_category_and_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        $a = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/a', 'issues' => ['missing_title']]);
        $b = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/b', 'issues' => ['broken_link']]);
        $c = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/c', 'issues' => []]);

        // Catalogue is exposed, grouped, with per-category counts.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Crawls/Show')
                ->has('catalog')
                ->where('catalog.page_title.count', 1)
                ->where('catalog.security.count', 0)
                ->has('pages.data', 3) // unfiltered = all URLs
            );

        // Filter by category → only pages with an active issue in it.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'page_title']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.group', 'page_title')
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/a')
            );

        // Filter by a single issue.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'links', 'issue' => 'broken_link']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.issue', 'broken_link')
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/b')
            );

        // A category with only planned checks yields no rows.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'security']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('pages.data', 0));
    }

    public function test_info_severity_code_is_filterable_but_not_counted_as_a_problem(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/secure', 'issues' => ['https_urls']]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // https_urls does not inflate the security problem badge …
                ->where('catalog.security.count', 0)
                // … but is per-code counted for the dropdown.
                ->where('catalog.security.checks', fn ($checks) => collect($checks)
                    ->firstWhere('code', 'https_urls')['count'] === 1)
            );

        // …and is a working filter that lists the page.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'security', 'issue' => 'https_urls']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/secure')
            );
    }

    public function test_page_detail_shows_where_a_url_was_found(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        $source = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/blog']);
        $target = CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/dead', 'status_code' => 404]);
        CrawlLink::factory()->create([
            'crawl_id' => $crawl->id, 'from_page_id' => $source->id,
            'to_url' => 'https://example.com/dead', 'type' => 'internal', 'anchor' => 'Read more',
        ]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.pages.show', ['project' => $project->id, 'crawl' => $crawl->id, 'page' => $target->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Crawls/Page')
                ->has('page.in_links', 1)
                ->where('page.in_links.0.from_url', 'https://example.com/blog')
                ->where('page.in_links.0.anchor', 'Read more')
            );
    }

    public function test_page_detail_exposes_issue_severities(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        $p = CrawlPage::factory()->for($crawl)->create(['issues' => ['missing_title', 'thin_content']]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.pages.show', ['project' => $project->id, 'crawl' => $crawl->id, 'page' => $p->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('page.issue_severities.missing_title', 'error')
                ->where('page.issue_severities.thin_content', 'notice')
            );
    }

    public function test_store_persists_capture_screenshots_flag(): void
    {
        Queue::fake();
        [$user, $project] = $this->member();

        $this->actingAs($user)->post(route('app.project.crawls.store', ['project' => $project->id]), [
            'start_url' => 'https://example.com',
            'mode' => 'full_site',
            'delay_ms' => 0,
            'capture_screenshots' => true,
        ])->assertRedirect();

        $this->assertTrue((bool) Crawl::first()->capture_screenshots);
    }

    public function test_serves_a_page_screenshot_scoped_to_the_project(): void
    {
        $diskName = config('filesystems.default');
        Storage::fake($diskName);

        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        $path = "crawl-screenshots/{$crawl->id}/p-desktop.jpg";
        Storage::disk($diskName)->put($path, 'JPEGDATA');
        $page = CrawlPage::factory()->for($crawl)->create(['screenshot_desktop_path' => $path]);

        $params = ['project' => $project->id, 'crawl' => $crawl->id, 'page' => $page->id, 'variant' => 'desktop'];
        $this->actingAs($user)->get(route('app.project.crawls.pages.screenshot', $params))->assertOk();

        // Variant with no stored screenshot → 404.
        $this->actingAs($user)
            ->get(route('app.project.crawls.pages.screenshot', [...$params, 'variant' => 'mobile']))
            ->assertNotFound();

        // A crawl in another project is not reachable through this project.
        $other = Crawl::factory()->for(Project::factory())->create();
        $this->actingAs($user)
            ->get(route('app.project.crawls.pages.screenshot', [...$params, 'crawl' => $other->id]))
            ->assertNotFound();
    }

    public function test_security_category_lists_pages_with_a_security_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/mixed', 'issues' => ['mixed_content', 'https_urls']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => ['https_urls']]);

        // Category badge counts only the page with a real problem (mixed_content), not the info-only page.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('catalog.security.count', 1));

        // The security group filter lists the affected page.
        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'security', 'issue' => 'mixed_content']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/mixed')
            );
    }

    public function test_url_category_lists_pages_with_a_url_issue(): void
    {
        [$user, $project] = $this->member();
        $crawl = Crawl::factory()->for($project)->create();
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/Foo_Bar', 'issues' => ['url_uppercase', 'url_underscores']]);
        CrawlPage::factory()->for($crawl)->create(['url' => 'https://example.com/clean', 'issues' => []]);

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('catalog.url.count', 1));

        $this->actingAs($user)
            ->get(route('app.project.crawls.show', ['project' => $project->id, 'crawl' => $crawl->id, 'group' => 'url', 'issue' => 'url_uppercase']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('pages.data', 1)
                ->where('pages.data.0.url', 'https://example.com/Foo_Bar')
            );
    }
}
