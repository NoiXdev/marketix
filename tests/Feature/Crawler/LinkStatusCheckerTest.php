<?php

namespace Tests\Feature\Crawler;

use App\Crawler\LinkStatusChecker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkStatusCheckerTest extends TestCase
{
    public function test_reports_status_of_reachable_url(): void
    {
        Http::fake(['example.com/*' => Http::response('', 404)]);

        $this->assertSame(404, (new LinkStatusChecker)->status('https://example.com/missing'));
    }

    public function test_ok_url_reports_200(): void
    {
        Http::fake(['example.com/*' => Http::response('', 200)]);

        $this->assertSame(200, (new LinkStatusChecker)->status('https://example.com/ok'));
    }

    public function test_falls_back_to_get_when_head_is_not_allowed(): void
    {
        Http::fake(fn ($request) => $request->method() === 'HEAD'
            ? Http::response('', 405)
            : Http::response('', 200));

        $this->assertSame(200, (new LinkStatusChecker)->status('https://example.com/no-head'));
    }

    public function test_skips_private_and_non_http_targets(): void
    {
        Http::fake(); // any real attempt would be intercepted; we assert none is made

        $this->assertNull((new LinkStatusChecker)->status('http://127.0.0.1/'));
        $this->assertNull((new LinkStatusChecker)->status('http://192.168.0.5/'));
        $this->assertNull((new LinkStatusChecker)->status('ftp://example.com/'));

        Http::assertNothingSent();
    }
}
