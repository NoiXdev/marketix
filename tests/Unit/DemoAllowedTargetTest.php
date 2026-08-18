<?php

namespace Tests\Unit;

use App\Rules\DemoAllowedTarget;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DemoAllowedTargetTest extends TestCase
{
    private function fails(string $value): bool
    {
        config([
            'demo.allowed_target_hosts' => ['example.com', 'github.com'],
            'app.domain' => 'marketix.test',
        ]);

        return Validator::make(['u' => $value], ['u' => [new DemoAllowedTarget]])->fails();
    }

    public function test_allowlisted_host_passes(): void
    {
        $this->assertFalse($this->fails('https://example.com/page'));
    }

    public function test_subdomain_of_allowlisted_host_passes(): void
    {
        $this->assertFalse($this->fails('https://docs.github.com/en'));
    }

    public function test_apps_own_domain_passes(): void
    {
        $this->assertFalse($this->fails('https://marketix.test/anything'));
    }

    public function test_foreign_host_fails(): void
    {
        $this->assertTrue($this->fails('https://evil-phishing.test/login'));
    }

    public function test_host_that_merely_ends_with_an_allowed_host_fails(): void
    {
        // "notexample.com" must NOT pass because it ends in "example.com".
        $this->assertTrue($this->fails('https://notexample.com/x'));
    }

    public function test_non_http_uris_pass_through(): void
    {
        $this->assertFalse($this->fails('mailto:hello@anywhere.test'));
        $this->assertFalse($this->fails('tel:+4915112345678'));
    }

    public function test_empty_value_passes(): void
    {
        $this->assertFalse($this->fails(''));
    }
}
