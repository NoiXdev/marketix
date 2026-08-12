<?php

namespace Tests\Unit\Crawler;

use App\Crawler\IssueCode;
use PHPUnit\Framework\TestCase;

class IssueCodeTest extends TestCase
{
    public function test_every_issue_code_has_a_known_severity(): void
    {
        foreach (IssueCode::cases() as $code) {
            $this->assertContains($code->severity(), ['error', 'warning', 'notice'], $code->value);
        }
    }

    public function test_server_error_is_an_error_and_thin_content_is_a_notice(): void
    {
        $this->assertSame('error', IssueCode::ServerError->severity());
        $this->assertSame('notice', IssueCode::ThinContent->severity());
    }
}
