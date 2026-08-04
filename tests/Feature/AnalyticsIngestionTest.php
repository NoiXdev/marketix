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
}
