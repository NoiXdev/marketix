<?php

namespace Tests\Feature;

use App\Enums\TrackingMode;
use App\Models\Event;
use App\Models\PageView;
use App\Models\Site;
use App\Support\VisitorHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class VisitorDataCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create(['domain' => 'example.com', 'tracking_mode' => TrackingMode::Hybrid, 'consent_signal' => 'UC_UI']);

        $this->hit(['path' => '/lp', 'visitor_id' => 'abc']);
        $this->hit(['path' => '/pricing', 'visitor_id' => 'abc']);
        $this->hit(['type' => 'event', 'name' => 'signup', 'props' => ['plan' => 'pro'], 'path' => '/pricing', 'visitor_id' => 'abc']);
        $this->hit(['path' => '/lp', 'visitor_id' => 'xyz'], '198.51.100.7');
        $this->hit(['path' => '/lp'], '192.0.2.4');
    }

    private function hit(array $payload, string $ip = '203.0.113.9'): void
    {
        $this->call('POST', route('app.analytics.event'), [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (TestAgent)',
        ], json_encode(['site' => $this->site->tracking_id] + $payload))->assertStatus(202);
    }

    public function test_export_prints_only_the_requested_visitors_data(): void
    {
        $this->assertSame(0, Artisan::call('marketix:visitor:export', ['site' => $this->site->tracking_id, 'visitor' => 'abc']));
        $export = json_decode(Artisan::output(), true);

        $this->assertSame('example.com', $export['site']);
        $this->assertCount(1, $export['visits']);
        $this->assertSame(['/lp', '/pricing'], array_column($export['page_views'], 'path'));
        $this->assertSame([['name' => 'signup', 'props' => ['plan' => 'pro']]], array_map(fn ($event) => ['name' => $event['name'], 'props' => $event['props']], $export['events']));
        $this->assertArrayNotHasKey('visitor_hash', $export['page_views'][0]);
    }

    public function test_forget_deletes_only_the_requested_visitor(): void
    {
        $this->artisan('marketix:visitor:forget', ['site' => $this->site->tracking_id, 'visitor' => 'abc', '--force' => true])
            ->expectsOutput('Deleted 1 visit, 2 page views and 1 event.')
            ->assertSuccessful();

        $abc = VisitorHash::forVisitorId('abc', $this->site->id);
        $this->assertSame(0, $this->site->visits()->where('visitor_hash', $abc)->count());
        $this->assertSame(0, PageView::where('visitor_hash', $abc)->count());
        $this->assertSame(0, Event::where('visitor_hash', $abc)->count());
        $this->assertSame(2, $this->site->visits()->count());
        $this->assertSame(2, PageView::where('site_id', $this->site->id)->count());
    }

    public function test_forget_asks_before_deleting(): void
    {
        $this->artisan('marketix:visitor:forget', ['site' => $this->site->tracking_id, 'visitor' => 'abc'])
            ->expectsConfirmation('Delete 1 visit, 2 page views and 1 event on example.com?', 'no')
            ->assertFailed();

        $this->assertSame(3, $this->site->visits()->count());
    }

    public function test_unknown_site_and_unknown_visitor_are_reported(): void
    {
        $this->artisan('marketix:visitor:forget', ['site' => 'nope', 'visitor' => 'abc'])
            ->expectsOutput('No site with this tracking ID.')
            ->assertFailed();

        $this->artisan('marketix:visitor:export', ['site' => $this->site->tracking_id, 'visitor' => 'nobody'])
            ->expectsOutput('No data stored for this visitor.')
            ->assertSuccessful();
    }
}
