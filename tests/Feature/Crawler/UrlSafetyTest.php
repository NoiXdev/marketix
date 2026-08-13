<?php

namespace Tests\Feature\Crawler;

use App\Crawler\UrlSafety;
use Tests\TestCase;

class UrlSafetyTest extends TestCase
{
    public function test_public_ip_is_safe(): void
    {
        $this->assertTrue(UrlSafety::hostIsSafe('93.184.216.34'));
    }

    public function test_loopback_is_unsafe(): void
    {
        $this->assertFalse(UrlSafety::hostIsSafe('127.0.0.1'));
    }

    public function test_private_class_a_is_unsafe(): void
    {
        $this->assertFalse(UrlSafety::hostIsSafe('10.0.0.1'));
    }

    public function test_link_local_metadata_ip_is_unsafe(): void
    {
        $this->assertFalse(UrlSafety::hostIsSafe('169.254.169.254'));
    }

    public function test_private_class_c_is_unsafe(): void
    {
        $this->assertFalse(UrlSafety::hostIsSafe('192.168.0.1'));
    }

    public function test_localhost_hostname_is_unsafe(): void
    {
        $this->assertFalse(UrlSafety::hostIsSafe('localhost'));
    }

    public function test_empty_host_is_unsafe(): void
    {
        $this->assertFalse(UrlSafety::hostIsSafe(''));
    }

    public function test_ipv6_loopback_is_unsafe(): void
    {
        $this->assertFalse(UrlSafety::hostIsSafe('::1'));
        // parse_url yields bracketed IPv6 literal hosts.
        $this->assertFalse(UrlSafety::hostIsSafe('[::1]'));
    }

    public function test_ipv6_link_local_and_unique_local_are_unsafe(): void
    {
        $this->assertFalse(UrlSafety::hostIsSafe('fe80::1'));
        $this->assertFalse(UrlSafety::hostIsSafe('fc00::1'));
    }

    public function test_ipv4_mapped_ipv6_private_is_unsafe(): void
    {
        // ::ffff:127.0.0.1 and ::ffff:169.254.169.254 must not slip through as "IPv6".
        $this->assertFalse(UrlSafety::hostIsSafe('::ffff:127.0.0.1'));
        $this->assertFalse(UrlSafety::hostIsSafe('[::ffff:169.254.169.254]'));
    }

    public function test_public_ipv6_is_safe(): void
    {
        $this->assertTrue(UrlSafety::hostIsSafe('2606:4700:4700::1111'));
        $this->assertTrue(UrlSafety::hostIsSafe('[2606:4700:4700::1111]'));
    }
}
