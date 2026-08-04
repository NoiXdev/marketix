<?php

namespace Tests\Feature;

use App\Jobs\RecordPageViewJob;
use App\Models\Site;
use App\Support\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecordPageViewJobTest extends TestCase
{
    use RefreshDatabase;

    private function dispatch(Site $site, string $path, string $hash = 'visitor-1', string $ua = 'Mozilla/5.0 (Macintosh)'): void
    {
        (new RecordPageViewJob(
            $site->id,
            $site->project_id,
            $hash,
            $ua,
            $path,
            'https://google.com/search',
            'en',
            ['country' => 'Germany', 'city' => 'Berlin', 'country_code' => 'DE'],
        ))->handle();
    }

    public function test_it_records_a_page_view_and_opens_a_visit(): void
    {
        $site = Site::factory()->create();

        $this->dispatch($site, '/home');

        $this->assertDatabaseHas('page_views', [
            'site_id' => $site->id,
            'path' => '/home',
            'country' => 'Germany',
            'country_code' => 'DE',
            'referer_domain' => 'google.com',
            'language' => 'en',
            'is_bot' => false,
        ]);
        $this->assertDatabaseHas('visits', [
            'site_id' => $site->id,
            'visitor_hash' => 'visitor-1',
            'entry_path' => '/home',
            'exit_path' => '/home',
            'pageview_count' => 1,
        ]);
    }

    public function test_second_view_within_window_continues_same_visit(): void
    {
        $site = Site::factory()->create();

        $this->dispatch($site, '/home');
        $this->dispatch($site, '/pricing');

        $this->assertSame(1, $site->visits()->count());
        $visit = $site->visits()->first();
        $this->assertSame(2, $visit->pageview_count);
        $this->assertSame('/home', $visit->entry_path);
        $this->assertSame('/pricing', $visit->exit_path);
        $this->assertSame(2, $site->pageViews()->count());
    }

    public function test_view_after_window_starts_new_visit(): void
    {
        $site = Site::factory()->create();

        Carbon::setTestNow(now());
        $this->dispatch($site, '/home');

        Carbon::setTestNow(now()->addMinutes(31));
        $this->dispatch($site, '/pricing');
        Carbon::setTestNow();

        $this->assertSame(2, $site->visits()->count());
    }

    public function test_different_visitor_gets_own_visit(): void
    {
        $site = Site::factory()->create();

        $this->dispatch($site, '/home', 'visitor-1');
        $this->dispatch($site, '/home', 'visitor-2');

        $this->assertSame(2, $site->visits()->count());
    }

    public function test_bot_user_agent_is_flagged(): void
    {
        $site = Site::factory()->create();

        $this->dispatch($site, '/home', 'bot-1', 'Googlebot/2.1 (+http://www.google.com/bot.html)');

        $this->assertDatabaseHas('page_views', ['site_id' => $site->id, 'is_bot' => true]);
        $this->assertDatabaseHas('visits', ['site_id' => $site->id, 'is_bot' => true]);
    }

    public function test_user_agent_device_classifier(): void
    {
        $this->assertSame('Mobile', UserAgent::device('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'));
        $this->assertSame('Tablet', UserAgent::device('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'));
        $this->assertSame('Desktop', UserAgent::device('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'));
    }
}
