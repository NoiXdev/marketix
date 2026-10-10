<?php

namespace Tests\Feature;

use App\Support\Cloudflare\CloudflareIpRanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UpdateCloudflareIpRangesTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/marketix-cloudflare-'.bin2hex(random_bytes(4));
        config(['cloudflare.ranges_path' => $this->dir.'/ip-ranges.json']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    /** @param array<string, mixed> $body */
    private function fakeApi(array $body, int $status = 200): void
    {
        Http::fake([CloudflareIpRanges::API_URL => Http::response($body, $status)]);
    }

    private function published(array $ipv4, array $ipv6 = []): array
    {
        return ['success' => true, 'result' => ['ipv4_cidrs' => $ipv4, 'ipv6_cidrs' => $ipv6]];
    }

    public function test_saves_the_published_ranges(): void
    {
        $this->fakeApi($this->published(['198.51.100.0/24', '162.158.0.0/15'], ['2606:4700::/32']));

        $this->artisan('marketix:cloudflare:update')
            ->expectsOutputToContain('Saved 2 IPv4 and 1 IPv6 ranges')
            ->assertSuccessful();

        $this->assertSame(['198.51.100.0/24', '162.158.0.0/15', '2606:4700::/32'], app(CloudflareIpRanges::class)->all());
    }

    public function test_the_client_ip_middleware_trusts_newly_published_ranges(): void
    {
        $this->fakeApi($this->published(['198.51.100.0/24']));
        $this->artisan('marketix:cloudflare:update')->assertSuccessful();
        Route::get('/_test/client-ip', fn (Request $request) => $request->ip());

        $ip = $this->call('GET', '/_test/client-ip', server: [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.7',
        ])->getContent();

        $this->assertSame('203.0.113.7', $ip);
    }

    public function test_keeps_the_current_list_when_the_download_fails(): void
    {
        app(CloudflareIpRanges::class)->store(['198.51.100.0/24']);
        $this->fakeApi(['success' => false], 500);

        $this->artisan('marketix:cloudflare:update')->assertFailed();

        $this->assertSame(['198.51.100.0/24'], app(CloudflareIpRanges::class)->all());
    }

    public function test_rejects_malformed_responses(): void
    {
        app(CloudflareIpRanges::class)->store(['198.51.100.0/24']);

        foreach ([
            ['success' => false, 'result' => ['ipv4_cidrs' => ['1.2.3.0/24']]],
            $this->published([]),
            $this->published(['1.2.3.0/24', 'not-a-range']),
            $this->published(['1.2.3.0/33']),
        ] as $body) {
            $this->fakeApi($body);
            $this->artisan('marketix:cloudflare:update')->assertFailed();
        }

        $this->assertSame(['198.51.100.0/24'], app(CloudflareIpRanges::class)->all());
    }

    public function test_falls_back_to_the_bundled_list_without_a_valid_file(): void
    {
        $this->assertSame(config('cloudflare.ip_ranges'), app(CloudflareIpRanges::class)->all());

        mkdir($this->dir);
        file_put_contents(config('cloudflare.ranges_path'), 'not json');

        $this->assertSame(config('cloudflare.ip_ranges'), app(CloudflareIpRanges::class)->all());
    }

    public function test_cidr_validation(): void
    {
        foreach (['162.158.0.0/15', '2606:4700::/32', '10.0.0.1/32'] as $valid) {
            $this->assertTrue(CloudflareIpRanges::isCidr($valid), $valid);
        }
        foreach (['162.158.0.0', '162.158.0.0/33', '2606:4700::/129', 'x/8', '1.2.3.4/-1', '1.2.3.4/8/8', null] as $invalid) {
            $this->assertFalse(CloudflareIpRanges::isCidr($invalid), (string) $invalid);
        }
    }

    public function test_the_update_is_scheduled_daily(): void
    {
        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression('/0\s+0 \* \* \*\s+php artisan marketix:cloudflare:update/', Artisan::output());
    }
}
