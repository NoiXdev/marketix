<?php

namespace Tests\Feature;

use App\Enums\PixelProvider;
use App\Models\Activity;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Funnel;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Project;
use App\Models\QrTemplate;
use App\Models\ScheduledReport;
use App\Models\Site;
use App\Models\Statistic;
use App\Models\Url;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveToProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $source;

    private Project $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->source = Project::create(['name' => 'Source']);
        $this->target = Project::create(['name' => 'Target']);
        $this->user->projects()->attach([$this->source->id, $this->target->id]);
    }

    private function link(Domain $domain, string $slug = 'promo'): Url
    {
        return Url::factory()->create([
            'project_id' => $this->source->id,
            'domain_id' => $domain->id,
            'user_id' => $this->user->id,
            'slug' => $slug,
        ]);
    }

    private function report(string $type, string $subjectId): ScheduledReport
    {
        return ScheduledReport::create([
            'project_id' => $this->source->id,
            'created_by' => $this->user->id,
            'name' => 'Weekly',
            'type' => $type,
            'subject_id' => $subjectId,
            'frequency' => 'weekly',
            'weekday' => 1,
            'period' => 'last_7_days',
            'formats' => ['csv' => true, 'pdf' => false],
            'recipients' => ['a@example.test'],
        ]);
    }

    public function test_link_moves_with_its_statistics_qr_code_and_reports(): void
    {
        $url = $this->link(Domain::create(['project_id' => $this->source->id, 'name' => 'source.test']));
        $targetDomain = Domain::create(['project_id' => $this->target->id, 'name' => 'target.test']);
        Statistic::factory()->forUrl($url)->create();
        Statistic::factory()->forUrl($url)->create()->delete();
        $pixel = $this->source->pixels()->create(['provider' => PixelProvider::cases()[0]->value, 'name' => 'P', 'tag' => 'TAG']);
        $url->pixels()->attach($pixel);
        $qrCode = $this->source->qrCodes()->create([
            'url_id' => $url->id, 'name' => 'QR', 'type' => 'url', 'is_dynamic' => true,
            'content' => ['url' => 'https://example.com'], 'style' => ['foreground' => '#000'],
        ]);
        $version = $qrCode->versions()->create([
            'version' => 1, 'name' => 'QR', 'type' => 'url', 'is_dynamic' => true, 'domain_id' => $url->domain_id, 'slug' => 'promo',
            'content' => ['url' => 'https://example.com'], 'style' => ['foreground' => '#000'],
        ]);
        $report = $this->report('link', $url->id);

        $this->actingAs($this->user)
            ->post(route('app.project.links.move', ['project' => $this->source->id, 'url' => $url->id]), [
                'project_id' => $this->target->id,
                'domain_id' => $targetDomain->id,
            ])
            ->assertRedirect(route('app.project.links.index', ['project' => $this->source->id]))
            ->assertSessionHas('success');

        $url->refresh();
        $this->assertSame($this->target->id, $url->project_id);
        $this->assertSame($targetDomain->id, $url->domain_id);
        $this->assertSame(2, Statistic::withTrashed()->where('project_id', $this->target->id)->count());
        $this->assertSame(0, $url->pixels()->count());
        $this->assertSame($this->target->id, $qrCode->fresh()->project_id);
        $this->assertSame($targetDomain->id, $version->fresh()->domain_id);
        $this->assertSame($this->target->id, $report->fresh()->project_id);
        $this->assertSame($this->source->id, $pixel->fresh()->project_id);
        $this->assertTrue(Activity::where('project_id', $this->source->id)->where('description', 'moved_out')->exists());
        $this->assertTrue(Activity::where('project_id', $this->target->id)->where('description', 'moved_in')->exists());
    }

    public function test_link_cannot_take_a_slug_that_is_used_on_the_target_domain(): void
    {
        $url = $this->link(Domain::create(['project_id' => $this->source->id, 'name' => 'source.test']));
        $targetDomain = Domain::create(['project_id' => $this->target->id, 'name' => 'target.test']);
        Url::factory()->create(['project_id' => $this->target->id, 'domain_id' => $targetDomain->id, 'slug' => 'promo'])->delete();

        $this->actingAs($this->user)
            ->post(route('app.project.links.move', ['project' => $this->source->id, 'url' => $url->id]), [
                'project_id' => $this->target->id,
                'domain_id' => $targetDomain->id,
            ])
            ->assertSessionHasErrors('domain_id');

        $this->assertSame($this->source->id, $url->fresh()->project_id);
    }

    public function test_link_needs_a_domain_of_the_target_project(): void
    {
        $sourceDomain = Domain::create(['project_id' => $this->source->id, 'name' => 'source.test']);
        $url = $this->link($sourceDomain);

        $this->actingAs($this->user)
            ->post(route('app.project.links.move', ['project' => $this->source->id, 'url' => $url->id]), [
                'project_id' => $this->target->id,
                'domain_id' => $sourceDomain->id,
            ])
            ->assertSessionHasErrors('domain_id');

        $this->assertSame($this->source->id, $url->fresh()->project_id);
    }

    public function test_target_must_be_another_accessible_project(): void
    {
        $pixel = $this->source->pixels()->create(['provider' => PixelProvider::cases()[0]->value, 'name' => 'P', 'tag' => 'TAG']);
        $foreign = Project::create(['name' => 'Foreign']);

        foreach ([$foreign->id, $this->source->id, 'missing'] as $projectId) {
            $this->actingAs($this->user)
                ->post(route('app.project.pixels.move', ['project' => $this->source->id, 'pixel' => $pixel->id]), ['project_id' => $projectId])
                ->assertSessionHasErrors('project_id');
        }

        $this->assertSame($this->source->id, $pixel->fresh()->project_id);
    }

    public function test_items_of_other_projects_are_not_found(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->user)
            ->post(route('app.project.sites.move', ['project' => $this->source->id, 'site' => $site->id]), ['project_id' => $this->target->id])
            ->assertNotFound();
    }

    public function test_domain_moves_with_all_its_links(): void
    {
        $domain = Domain::create(['project_id' => $this->source->id, 'name' => 'source.test']);
        $first = $this->link($domain, 'first');
        $second = $this->link($domain, 'second');
        $second->delete();
        Statistic::factory()->forUrl($first)->create();

        $this->actingAs($this->user)
            ->post(route('app.project.domains.move', ['project' => $this->source->id, 'domain' => $domain->id]), ['project_id' => $this->target->id])
            ->assertRedirect(route('app.project.domains.index', ['project' => $this->source->id]));

        $this->assertSame($this->target->id, $domain->fresh()->project_id);
        $this->assertSame($this->target->id, $first->fresh()->project_id);
        $this->assertSame($this->target->id, Url::withTrashed()->find($second->id)->project_id);
        $this->assertSame($this->target->id, Statistic::where('url_id', $first->id)->value('project_id'));
    }

    public function test_site_moves_with_its_analytics_data(): void
    {
        $site = Site::factory()->forProject($this->source)->create();
        $visit = Visit::factory()->forSite($site)->create();
        PageView::factory()->forVisit($visit)->create();
        Event::factory()->forVisit($visit)->create();
        Goal::factory()->forSite($site)->create();
        Funnel::factory()->forSite($site)->create();
        $report = $this->report('site_analytics', $site->id);

        $this->actingAs($this->user)
            ->post(route('app.project.sites.move', ['project' => $this->source->id, 'site' => $site->id]), ['project_id' => $this->target->id])
            ->assertRedirect(route('app.project.sites.index', ['project' => $this->source->id]));

        $this->assertSame($this->target->id, $site->fresh()->project_id);

        foreach ([Visit::class, PageView::class, Event::class, Goal::class, Funnel::class] as $model) {
            $this->assertSame([$this->target->id], $model::where('site_id', $site->id)->pluck('project_id')->unique()->values()->all(), $model);
        }

        $this->assertSame($this->target->id, $report->fresh()->project_id);
    }

    public function test_pixel_is_detached_from_source_links(): void
    {
        $url = $this->link(Domain::create(['project_id' => $this->source->id, 'name' => 'source.test']));
        $pixel = $this->source->pixels()->create(['provider' => PixelProvider::cases()[0]->value, 'name' => 'P', 'tag' => 'TAG']);
        $url->pixels()->attach($pixel);

        $this->actingAs($this->user)
            ->post(route('app.project.pixels.move', ['project' => $this->source->id, 'pixel' => $pixel->id]), ['project_id' => $this->target->id])
            ->assertRedirect(route('app.project.pixels.index', ['project' => $this->source->id]));

        $this->assertSame($this->target->id, $pixel->fresh()->project_id);
        $this->assertSame(0, $url->pixels()->count());
    }

    public function test_static_qr_code_moves_without_a_domain(): void
    {
        $qrCode = $this->source->qrCodes()->create([
            'name' => 'QR', 'type' => 'text', 'is_dynamic' => false,
            'content' => ['text' => 'hi'], 'style' => ['foreground' => '#000'],
        ]);

        $this->actingAs($this->user)
            ->post(route('app.project.qrcodes.move', ['project' => $this->source->id, 'qrCode' => $qrCode->id]), ['project_id' => $this->target->id])
            ->assertRedirect(route('app.project.qrcodes.index', ['project' => $this->source->id]));

        $this->assertSame($this->target->id, $qrCode->fresh()->project_id);
    }

    public function test_dynamic_qr_code_moves_its_short_link(): void
    {
        $url = $this->link(Domain::create(['project_id' => $this->source->id, 'name' => 'source.test']));
        $targetDomain = Domain::create(['project_id' => $this->target->id, 'name' => 'target.test']);
        $qrCode = $this->source->qrCodes()->create([
            'url_id' => $url->id, 'name' => 'QR', 'type' => 'url', 'is_dynamic' => true,
            'content' => ['url' => 'https://example.com'], 'style' => ['foreground' => '#000'],
        ]);

        $this->actingAs($this->user)
            ->post(route('app.project.qrcodes.move', ['project' => $this->source->id, 'qrCode' => $qrCode->id]), ['project_id' => $this->target->id])
            ->assertSessionHasErrors('domain_id');

        $this->actingAs($this->user)
            ->post(route('app.project.qrcodes.move', ['project' => $this->source->id, 'qrCode' => $qrCode->id]), [
                'project_id' => $this->target->id,
                'domain_id' => $targetDomain->id,
            ])
            ->assertRedirect(route('app.project.qrcodes.index', ['project' => $this->source->id]));

        $this->assertSame($this->target->id, $qrCode->fresh()->project_id);
        $this->assertSame($targetDomain->id, $url->fresh()->domain_id);
    }

    public function test_qr_template_moves(): void
    {
        $template = QrTemplate::create(['project_id' => $this->source->id, 'name' => 'Brand', 'style' => ['foreground' => '#000']]);

        $this->actingAs($this->user)
            ->post(route('app.project.qr-templates.move', ['project' => $this->source->id, 'qrTemplate' => $template->id]), ['project_id' => $this->target->id])
            ->assertRedirect(route('app.project.qr-templates.index', ['project' => $this->source->id]));

        $this->assertSame($this->target->id, $template->fresh()->project_id);
    }

    public function test_domain_options_list_the_domains_of_an_accessible_project(): void
    {
        Domain::create(['project_id' => $this->target->id, 'name' => 'b.test']);
        Domain::create(['project_id' => $this->target->id, 'name' => 'a.test']);
        $foreign = Project::create(['name' => 'Foreign']);

        $this->actingAs($this->user)
            ->getJson(route('app.project.domains.options', ['project' => $this->target->id]))
            ->assertOk()
            ->assertJsonPath('domains.*.name', ['a.test', 'b.test']);

        $this->actingAs($this->user)
            ->getJson(route('app.project.domains.options', ['project' => $foreign->id]))
            ->assertForbidden();
    }
}
