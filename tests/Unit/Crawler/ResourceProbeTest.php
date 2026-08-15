<?php

namespace Tests\Unit\Crawler;

use App\Crawler\ResourceProbe;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResourceProbeTest extends TestCase
{
    public function test_head_with_content_length(): void
    {
        Http::fake(['*' => Http::response('', 200, ['Content-Length' => '2048'])]);
        $this->assertSame(['status' => 200, 'size' => 2048], (new ResourceProbe)->probe('https://example.com/a.js'));
    }

    public function test_head_405_falls_back_to_get(): void
    {
        Http::fakeSequence()
            ->push('', 405)
            ->push('', 200, ['Content-Length' => '999']);
        $this->assertSame(['status' => 200, 'size' => 999], (new ResourceProbe)->probe('https://example.com/b.css'));
    }

    public function test_no_content_length_uses_get_body_length(): void
    {
        Http::fakeSequence()
            ->push('', 200)                 // HEAD: 200 but no Content-Length
            ->push('abcd', 200);            // GET: no Content-Length → body length 4
        $this->assertSame(['status' => 200, 'size' => 4], (new ResourceProbe)->probe('https://example.com/c.png'));
    }

    public function test_unsafe_host_is_not_probed(): void
    {
        Http::fake();
        $this->assertSame(['status' => null, 'size' => null], (new ResourceProbe)->probe('http://127.0.0.1/x.js'));
        Http::assertNothingSent();
    }
}
