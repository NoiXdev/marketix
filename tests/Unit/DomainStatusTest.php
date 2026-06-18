<?php

namespace Tests\Unit;

use App\Models\Domain;
use Tests\TestCase;

class DomainStatusTest extends TestCase
{
    public function test_status_is_pending_when_unchecked(): void
    {
        $domain = new Domain(['name' => 'go.example.com']);

        $this->assertSame('pending', $domain->status);
    }

    public function test_status_is_healthy_when_all_checks_pass(): void
    {
        $domain = new Domain([
            'name' => 'go.example.com',
            'dns_ok' => true,
            'reachable_ok' => true,
            'ssl_ok' => true,
        ]);

        $this->assertSame('healthy', $domain->status);
    }

    public function test_status_is_error_when_any_check_fails(): void
    {
        $domain = new Domain([
            'name' => 'go.example.com',
            'dns_ok' => true,
            'reachable_ok' => false,
            'ssl_ok' => null,
        ]);

        $this->assertSame('error', $domain->status);
    }

    public function test_status_is_pending_when_partially_checked_without_failure(): void
    {
        $domain = new Domain([
            'name' => 'go.example.com',
            'dns_ok' => true,
            'reachable_ok' => null,
            'ssl_ok' => null,
        ]);

        $this->assertSame('pending', $domain->status);
    }
}
