<?php

namespace Tests\Feature;

use App\Enums\TrackingMode;
use App\Jobs\RecordPageViewJob;
use App\Models\Site;
use App\Services\GeoIpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CloudflareClientIpTest extends TestCase
{
    use RefreshDatabase;

    private const REVERSE_PROXY = '172.18.0.5';

    private const CLOUDFLARE_EDGE = '162.158.88.12';

    private const VISITOR = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();

        // Always the bundled list, never a file downloaded in this environment
        config(['cloudflare.ranges_path' => sys_get_temp_dir().'/marketix-missing-cloudflare-ranges.json']);
        Route::get('/_test/client-ip', fn (Request $request) => $request->ip());
    }

    /** @param array<string, string> $server */
    private function clientIp(array $server): string
    {
        return $this->call('GET', '/_test/client-ip', server: $server)->getContent();
    }

    public function test_uses_cf_connecting_ip_when_cloudflare_reaches_the_reverse_proxy(): void
    {
        $this->assertSame(self::VISITOR, $this->clientIp([
            'REMOTE_ADDR' => self::REVERSE_PROXY,
            'HTTP_X_FORWARDED_FOR' => self::CLOUDFLARE_EDGE,
            'HTTP_CF_CONNECTING_IP' => self::VISITOR,
        ]));
    }

    public function test_handles_reverse_proxies_that_append_to_forwarded_for(): void
    {
        $this->assertSame(self::VISITOR, $this->clientIp([
            'REMOTE_ADDR' => self::REVERSE_PROXY,
            'HTTP_X_FORWARDED_FOR' => self::VISITOR.', '.self::CLOUDFLARE_EDGE,
            'HTTP_CF_CONNECTING_IP' => self::VISITOR,
        ]));
    }

    public function test_uses_cf_connecting_ip_when_cloudflare_connects_directly(): void
    {
        $this->assertSame(self::VISITOR, $this->clientIp([
            'REMOTE_ADDR' => self::CLOUDFLARE_EDGE,
            'HTTP_X_FORWARDED_FOR' => self::VISITOR,
            'HTTP_CF_CONNECTING_IP' => self::VISITOR,
        ]));
    }

    public function test_supports_ipv6(): void
    {
        $this->assertSame('2001:db8::1', $this->clientIp([
            'REMOTE_ADDR' => self::REVERSE_PROXY,
            'HTTP_X_FORWARDED_FOR' => '2a06:98c1:3120::3',
            'HTTP_CF_CONNECTING_IP' => '2001:db8::1',
        ]));
    }

    public function test_ignores_a_forged_header_that_did_not_come_through_cloudflare(): void
    {
        $this->assertSame('198.51.100.9', $this->clientIp([
            'REMOTE_ADDR' => self::REVERSE_PROXY,
            'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
            'HTTP_CF_CONNECTING_IP' => self::VISITOR,
        ]));
    }

    public function test_ignores_an_invalid_header_value(): void
    {
        $this->assertSame(self::CLOUDFLARE_EDGE, $this->clientIp([
            'REMOTE_ADDR' => self::REVERSE_PROXY,
            'HTTP_X_FORWARDED_FOR' => self::CLOUDFLARE_EDGE,
            'HTTP_CF_CONNECTING_IP' => 'not-an-ip',
        ]));
    }

    public function test_requests_without_cloudflare_keep_the_forwarded_address(): void
    {
        $this->assertSame('198.51.100.9', $this->clientIp([
            'REMOTE_ADDR' => self::REVERSE_PROXY,
            'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
        ]));
    }

    public function test_analytics_ingestion_geolocates_the_visitor_instead_of_cloudflare(): void
    {
        Queue::fake([RecordPageViewJob::class]);
        $geo = new class extends GeoIpService
        {
            /** @var list<string> */
            public array $lookups = [];

            public function __construct() {}

            public function lookup(string $ip): array
            {
                $this->lookups[] = $ip;

                return ['country' => null, 'city' => null, 'country_code' => null, 'region' => null, 'subdivision_code' => null];
            }
        };
        $this->app->instance(GeoIpService::class, $geo);
        $site = Site::factory()->create(['tracking_mode' => TrackingMode::Cookieless]);

        $this->call('POST', route('app.analytics.event'), server: [
            'REMOTE_ADDR' => self::REVERSE_PROXY,
            'HTTP_X_FORWARDED_FOR' => self::CLOUDFLARE_EDGE,
            'HTTP_CF_CONNECTING_IP' => self::VISITOR,
            'CONTENT_TYPE' => 'text/plain',
        ], content: json_encode(['site' => $site->tracking_id, 'path' => '/']))->assertStatus(202);

        $this->assertSame([self::VISITOR], $geo->lookups);
    }
}
