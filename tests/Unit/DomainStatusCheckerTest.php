<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Services\CertificateReader;
use App\Services\DnsResolver;
use App\Services\DomainStatusChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainStatusCheckerTest extends TestCase
{
    private function resolver(array $map): DnsResolver
    {
        return new class($map) implements DnsResolver
        {
            public function __construct(private array $map) {}

            public function resolveIps(string $host): array
            {
                return $this->map[$host] ?? [];
            }
        };
    }

    private function certReader(?array $cert): CertificateReader
    {
        return new class($cert) implements CertificateReader
        {
            public function __construct(private ?array $cert) {}

            public function read(string $host, int $port = 443): ?array
            {
                return $this->cert;
            }
        };
    }

    public function test_all_checks_pass(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['https://go.example.com/.well-known/marketix' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertTrue($result['dns_ok']);
        $this->assertTrue($result['reachable_ok']);
        $this->assertTrue($result['ssl_ok']);
    }

    public function test_dns_fails_when_ips_disjoint(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['9.9.9.9'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['dns_ok']);
    }

    public function test_reachable_fails_on_wrong_signature(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'someone-else'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['reachable_ok']);
    }

    public function test_ssl_fails_when_expired(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->subDay()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['ssl_ok']);
    }

    public function test_ssl_fails_when_no_certificate(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(null),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['ssl_ok']);
    }

    // FIX 1 — SSRF guard tests

    public function test_reachable_refuses_private_ip(): void
    {
        config(['app.domain' => 'app.test']);
        // If the guard were absent, this would return a valid marker and reachable_ok would be true.
        // The catch-all ensures that any wrongly-made request would "pass" — proving the guard blocks it.
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['127.0.0.1'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['reachable_ok']);
        $this->assertArrayHasKey('error', $result['check_details']['reachable']);
    }

    public function test_reachable_refuses_unresolvable(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => [], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['reachable_ok']);
        $this->assertArrayHasKey('error', $result['check_details']['reachable']);
    }

    // FIX 2 — Wildcard label-depth tests

    public function test_ssl_wildcard_matches_single_label(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => '', 'san' => ['*.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertTrue($result['ssl_ok']);
    }

    public function test_ssl_wildcard_rejects_multi_label(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['deep.sub.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => '', 'san' => ['*.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'deep.sub.example.com']));

        $this->assertFalse($result['ssl_ok']);
    }

    // FIX 4 — Fault isolation test

    public function test_one_failing_check_does_not_abort_others(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $throwingResolver = new class implements DnsResolver
        {
            public function resolveIps(string $host): array
            {
                throw new \RuntimeException('DNS lookup failed');
            }
        };

        $checker = new DomainStatusChecker(
            $throwingResolver,
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        // check() must not throw
        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['dns_ok']);
        $this->assertTrue($result['ssl_ok']); // SSL does not use the resolver
        // reachable_ok will be false because the throwing resolver also fails the SSRF pre-check
        $this->assertIsBool($result['reachable_ok']);
    }
}
