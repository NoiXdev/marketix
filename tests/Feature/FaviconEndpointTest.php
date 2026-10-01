<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaviconEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\nfake-favicon-bytes";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function resolveTo(array $map): void
    {
        $this->app->instance(DnsResolver::class, new class($map) implements DnsResolver
        {
            public function __construct(private array $map) {}

            public function resolveIps(string $host): array
            {
                return $this->map[$host] ?? [];
            }
        });
    }

    public function test_guests_cannot_fetch_favicons(): void
    {
        $this->get(route('app.favicon.show', ['domain' => 'example.com']))
            ->assertRedirect(route('app.auth.show-login'));
    }

    public function test_it_serves_and_caches_a_domains_favicon(): void
    {
        $this->resolveTo(['example.com' => ['93.184.216.34']]);
        Http::fake([
            'https://example.com/favicon.ico' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->create();

        $first = $this->actingAs($user)->get(route('app.favicon.show', ['domain' => 'example.com']));
        $first->assertOk();
        $this->assertSame('image/png', $first->headers->get('Content-Type'));
        $this->assertSame(self::PNG, $first->getContent());

        // Second request is served from the disk cache — no extra HTTP call.
        $this->actingAs($user)->get(route('app.favicon.show', ['domain' => 'example.com']))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_it_refuses_to_probe_private_addresses(): void
    {
        $this->resolveTo(['internal.test' => ['10.0.0.5']]);
        Http::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.favicon.show', ['domain' => 'internal.test']));

        // Falls back to the neutral globe SVG and never touches the network.
        $response->assertOk();
        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        Http::assertNothingSent();
    }

    public function test_it_discovers_the_icon_from_the_homepage_when_favicon_ico_is_missing(): void
    {
        $this->resolveTo(['example.org' => ['93.184.216.34']]);
        Http::fake([
            'https://example.org/favicon.ico' => Http::response('', 404),
            'https://example.org/' => Http::response('<html><head><link rel="shortcut icon" href="/assets/icon.png"></head></html>', 200, ['Content-Type' => 'text/html']),
            'https://example.org/assets/icon.png' => Http::response(self::PNG, 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.favicon.show', ['domain' => 'example.org']));
        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame(self::PNG, $response->getContent());
    }

    public function test_it_falls_back_to_a_globe_when_no_icon_exists(): void
    {
        $this->resolveTo(['noicons.test' => ['93.184.216.34']]);
        Http::fake([
            'https://noicons.test/favicon.ico' => Http::response('', 404),
            'https://noicons.test/' => Http::response('<html><head></head></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.favicon.show', ['domain' => 'noicons.test']));
        $response->assertOk();
        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
    }
}
