<?php

namespace Tests\Feature;

use App\Enums\ConsentMode;
use App\Enums\TrackingMode;
use App\Jobs\RecordPageViewJob;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_endpoint_returns_site_mode(): void
    {
        $site = Site::factory()->create([
            'tracking_mode' => TrackingMode::Cookie,
            'consent_mode' => ConsentMode::ThirdPartySignal,
            'consent_signal' => 'UC_UI',
            'respect_dnt' => true,
        ]);

        $this->getJson(route('app.analytics.config', ['trackingId' => $site->tracking_id]))
            ->assertOk()
            ->assertJson([
                'tracking_mode' => 'cookie',
                'consent_mode' => 'third_party_signal',
                'consent_signal' => 'UC_UI',
                'respect_dnt' => true,
            ]);
    }

    public function test_config_unknown_tracking_id_is_404(): void
    {
        $this->getJson(route('app.analytics.config', ['trackingId' => 'nope']))
            ->assertNotFound();
    }

    public function test_event_accepts_text_plain_beacon_body(): void
    {
        Queue::fake([RecordPageViewJob::class]);
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless]);

        // Mirrors the snippet's real transport: a raw JSON string sent with
        // Content-Type text/plain (CORS "simple request", no preflight).
        $body = json_encode(['site' => $site->tracking_id, 'path' => '/pricing', 'referrer' => null]);

        $this->call('POST', route('app.analytics.event'), [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], $body)->assertStatus(202);

        Queue::assertPushed(RecordPageViewJob::class);
    }

    public function test_cookieless_event_dispatches_job_and_returns_202(): void
    {
        Queue::fake([RecordPageViewJob::class]);
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless]);

        $this->postJson(route('app.analytics.event'), [
            'site' => $site->tracking_id,
            'path' => '/home',
            'referrer' => 'https://google.com/',
        ])->assertStatus(202);

        Queue::assertPushed(RecordPageViewJob::class);
    }

    public function test_unknown_site_is_silent_204_and_no_job(): void
    {
        Queue::fake([RecordPageViewJob::class]);

        $this->postJson(route('app.analytics.event'), ['site' => 'nope', 'path' => '/x'])
            ->assertStatus(204);

        Queue::assertNotPushed(RecordPageViewJob::class);
    }

    public function test_cookie_mode_without_visitor_id_is_204_no_job(): void
    {
        Queue::fake([RecordPageViewJob::class]);
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookie]);

        $this->postJson(route('app.analytics.event'), ['site' => $site->tracking_id, 'path' => '/home'])
            ->assertStatus(204);

        Queue::assertNotPushed(RecordPageViewJob::class);
    }

    public function test_cookie_mode_with_visitor_id_dispatches_job(): void
    {
        Queue::fake([RecordPageViewJob::class]);
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookie]);

        $this->postJson(route('app.analytics.event'), [
            'site' => $site->tracking_id,
            'path' => '/home',
            'visitor_id' => 'abc123',
        ])->assertStatus(202);

        Queue::assertPushed(RecordPageViewJob::class);
    }

    public function test_dnt_header_suppresses_tracking_when_enabled(): void
    {
        Queue::fake([RecordPageViewJob::class]);
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless, 'respect_dnt' => true]);

        $this->withHeaders(['DNT' => '1'])
            ->postJson(route('app.analytics.event'), ['site' => $site->tracking_id, 'path' => '/home'])
            ->assertStatus(204);

        Queue::assertNotPushed(RecordPageViewJob::class);
    }

    public function test_utm_is_stored_on_page_view_and_first_touch_on_visit(): void
    {
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless]);

        $body = json_encode([
            'site' => $site->tracking_id,
            'path' => '/lp',
            'utm' => ['source' => 'google', 'medium' => 'cpc', 'campaign' => 'summer'],
        ]);

        $this->call('POST', route('app.analytics.event'), [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)
            ->assertStatus(202);

        $this->assertDatabaseHas('page_views', ['site_id' => $site->id, 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'summer']);
        $this->assertDatabaseHas('visits', ['site_id' => $site->id, 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'summer']);
    }

    public function test_first_touch_utm_is_not_overwritten_within_session(): void
    {
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless]);

        // Same IP + UA => same daily visitor hash => same session within the window.
        $headers = ['CONTENT_TYPE' => 'text/plain', 'REMOTE_ADDR' => '203.0.113.5', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (TestAgent)'];

        $first = json_encode(['site' => $site->tracking_id, 'path' => '/lp', 'utm' => ['source' => 'google', 'medium' => 'cpc']]);
        $this->call('POST', route('app.analytics.event'), [], [], [], $headers, $first)->assertStatus(202);

        $second = json_encode(['site' => $site->tracking_id, 'path' => '/pricing', 'utm' => ['source' => 'newsletter', 'medium' => 'email']]);
        $this->call('POST', route('app.analytics.event'), [], [], [], $headers, $second)->assertStatus(202);

        // One visit, first-touch utm preserved; two page views with their own utm.
        $this->assertSame(1, $site->visits()->count());
        $this->assertDatabaseHas('visits', ['site_id' => $site->id, 'utm_source' => 'google', 'utm_medium' => 'cpc']);
        $this->assertDatabaseHas('page_views', ['site_id' => $site->id, 'path' => '/pricing', 'utm_source' => 'newsletter', 'utm_medium' => 'email']);
    }

    public function test_event_without_utm_leaves_columns_null(): void
    {
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless]);

        $body = json_encode(['site' => $site->tracking_id, 'path' => '/lp']);
        $this->call('POST', route('app.analytics.event'), [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertStatus(202);

        $this->assertDatabaseHas('page_views', ['site_id' => $site->id, 'utm_source' => null, 'utm_campaign' => null]);
        $this->assertDatabaseHas('visits', ['site_id' => $site->id, 'utm_source' => null, 'utm_campaign' => null]);
    }

    public function test_unknown_utm_keys_are_ignored(): void
    {
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless]);

        $body = json_encode(['site' => $site->tracking_id, 'path' => '/lp', 'utm' => ['source' => 'google', 'evil' => 'x']]);
        $this->call('POST', route('app.analytics.event'), [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertStatus(202);

        $this->assertDatabaseHas('page_views', ['site_id' => $site->id, 'utm_source' => 'google']);
    }

    public function test_empty_string_utm_is_normalized_to_null(): void
    {
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless]);

        $body = json_encode(['site' => $site->tracking_id, 'path' => '/lp', 'utm' => ['source' => '', 'medium' => 'cpc']]);
        $this->call('POST', route('app.analytics.event'), [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertStatus(202);

        $this->assertDatabaseHas('page_views', ['site_id' => $site->id, 'utm_source' => null, 'utm_medium' => 'cpc']);
        $this->assertDatabaseHas('visits', ['site_id' => $site->id, 'utm_source' => null, 'utm_medium' => 'cpc']);
    }
}
