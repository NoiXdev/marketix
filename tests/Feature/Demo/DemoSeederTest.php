<?php

namespace Tests\Feature\Demo;

use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Models\Domain;
use App\Models\Event;
use App\Models\Goal;
use App\Models\PageView;
use App\Models\Pixel;
use App\Models\Project;
use App\Models\QrCode;
use App\Models\QrTemplate;
use App\Models\ScheduledReport;
use App\Models\Site;
use App\Models\Statistic;
use App\Models\User;
use App\Settings\MailSettings;
use App\Settings\StorageSettings;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_demo_account_and_project(): void
    {
        $this->seed(DemoSeeder::class);

        $demo = User::query()->where('email', config('demo.email'))->first();
        $this->assertNotNull($demo);
        $this->assertTrue($demo->super_admin);

        $project = Project::query()->where('name', DemoSeeder::COMPANY)->first();
        $this->assertNotNull($project);
        $this->assertSame(4, $project->users()->count());
        $this->assertSame(1, Domain::query()->where('project_id', $project->id)->count());
    }

    public function test_the_settings_groups_survive_a_fresh_migrate(): void
    {
        // The settings migrations in database/settings/ re-create and seed
        // every property, so a reset never leaves the instance unconfigured.
        $this->assertNotSame('', app(MailSettings::class)->from_address);
        $this->assertNotSame('', app(StorageSettings::class)->driver);

        // BrandingSettings::appName() falls back to 'Marketix' whenever
        // app_name is null — which is also what spatie/laravel-settings
        // substitutes for a property that was never migrated at all, so
        // assertNotNull(appName()) can't tell "seeded" apart from "never
        // existed" (see app/Settings/BrandingSettings.php:27). Assert
        // directly against the settings table instead: the branding
        // migration adds exactly 5 rows (database/settings/2026_06_19_000000_create_branding_settings.php),
        // so their presence is proof the migration actually ran on this reset.
        $this->assertSame(
            5,
            DB::table('settings')->where('group', 'branding')->count(),
        );
    }

    public function test_it_seeds_links_with_recent_click_history(): void
    {
        $this->seed(DemoSeeder::class);

        $project = Project::query()->where('name', DemoSeeder::COMPANY)->firstOrFail();

        $this->assertGreaterThanOrEqual(15, $project->urls()->count());

        $stats = Statistic::query()->where('project_id', $project->id);
        $this->assertGreaterThan(500, $stats->count());

        // History reaches back roughly 90 days and up to today.
        $this->assertTrue($stats->clone()->where('created_at', '>=', now()->subDays(2))->exists());
        $this->assertTrue($stats->clone()->where('created_at', '<=', now()->subDays(80))->exists());

        // Country breakdowns need real ISO codes, not faker noise.
        $this->assertTrue($stats->clone()->whereNotNull('country_code')->exists());

        // Top Referrers needs more than one distinct value, or every link's
        // chart shows 100% self-referral and 0% Google/Instagram/LinkedIn/direct.
        $this->assertGreaterThan(1, $stats->clone()->distinct('domain')->count('domain'));

        // Unique clicks must actually be lower than total clicks, or the
        // list page and the detail page show contradictory numbers.
        $this->assertLessThan($stats->count(), $stats->clone()->distinct('visitor_hash')->count('visitor_hash'));

        // Clicks must be spread across the whole link inventory, not piled
        // onto a single URL.
        $this->assertGreaterThan(1, $stats->clone()->distinct('url_id')->count('url_id'));
    }

    public function test_it_seeds_qr_codes_templates_pixels_and_reports(): void
    {
        $this->seed(DemoSeeder::class);

        $project = Project::query()->where('name', DemoSeeder::COMPANY)->firstOrFail();

        $this->assertGreaterThanOrEqual(5, QrCode::query()->where('project_id', $project->id)->count());
        $this->assertSame(2, QrTemplate::query()->where('project_id', $project->id)->count());
        $this->assertGreaterThanOrEqual(2, Pixel::query()->where('project_id', $project->id)->count());
        $this->assertSame(2, ScheduledReport::query()->where('project_id', $project->id)->count());

        // At least one QR must be dynamic (and backed by a real link) or the
        // QR list's "Scans" column is a dash for every row, and the demo
        // shows no evidence QR scan tracking exists at all.
        $this->assertTrue(
            QrCode::query()->where('project_id', $project->id)->where('is_dynamic', true)->whereNotNull('url_id')->exists()
        );

        // The two pixels must carry distinct providers — a row-count
        // assertion alone would still pass if both silently fell back to
        // the factory default provider.
        $this->assertSame(
            2,
            Pixel::query()->where('project_id', $project->id)->distinct('provider')->count('provider')
        );

        // The WiFi QR's content must actually carry a network name, or it
        // encodes an empty/broken payload.
        $wifiQr = QrCode::query()->where('project_id', $project->id)->where('type', 'wifi')->firstOrFail();
        $this->assertNotEmpty($wifiQr->content['ssid'] ?? null);
    }

    public function test_it_seeds_an_analytics_site_with_history(): void
    {
        $this->seed(DemoSeeder::class);

        $project = Project::query()->where('name', DemoSeeder::COMPANY)->firstOrFail();
        $site = Site::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertGreaterThan(200, PageView::query()->where('site_id', $site->id)->count());
        $this->assertGreaterThan(0, Event::query()->where('site_id', $site->id)->count());
        $this->assertSame(2, Goal::query()->where('site_id', $site->id)->count());
    }

    public function test_it_seeds_two_completed_crawls(): void
    {
        $this->seed(DemoSeeder::class);

        $project = Project::query()->where('name', DemoSeeder::COMPANY)->firstOrFail();
        $crawls = Crawl::query()->where('project_id', $project->id)->get();

        $this->assertCount(2, $crawls);
        $this->assertTrue($crawls->every(fn ($c) => $c->status->value === 'completed'));
        $this->assertGreaterThan(0, CrawlPage::query()->whereIn('crawl_id', $crawls->pluck('id'))->count());
    }
}
