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
        return new class($map) implements DnsResolver {
            public function __construct(private array $map) {}

            public function resolveIps(string $host): array
            {
                return $this->map[$host] ?? [];
            }
        };
    }

    private function certReader(?array $cert): CertificateReader
    {
        return new class($cert) implements CertificateReader {
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
}
