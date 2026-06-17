<?php

namespace Tests\Unit;

use App\Support\QrTarget;
use PHPUnit\Framework\TestCase;

class QrTargetTest extends TestCase
{
    public function test_link_returns_raw_url(): void
    {
        $this->assertSame('https://example.com', QrTarget::redirectTarget('link', ['url' => 'https://example.com']));
    }

    public function test_email_builds_mailto_with_subject_and_body(): void
    {
        $target = QrTarget::redirectTarget('email', ['email' => 'a@b.com', 'subject' => 'Hi there', 'body' => 'Yo']);
        $this->assertSame('mailto:a@b.com?subject=Hi%20there&body=Yo', $target);
    }

    public function test_phone_builds_tel(): void
    {
        $this->assertSame('tel:+4912345', QrTarget::redirectTarget('phone', ['phone' => '+4912345']));
    }

    public function test_whatsapp_strips_non_digits(): void
    {
        $this->assertSame('https://wa.me/491234567890', QrTarget::redirectTarget('whatsapp', ['phone' => '+49 123 456 7890', 'message' => '']));
    }

    public function test_unknown_or_empty_returns_empty_string(): void
    {
        $this->assertSame('', QrTarget::redirectTarget('link', []));
        $this->assertSame('', QrTarget::redirectTarget('text', ['text' => 'hello']));
    }

    public function test_application_uses_first_non_empty_url(): void
    {
        $this->assertSame('https://play.google.com/x', QrTarget::redirectTarget('application', [
            'url_ios' => '', 'url_android' => 'https://play.google.com/x', 'url_fallback' => '',
        ]));
        $this->assertSame('https://fallback.example', QrTarget::redirectTarget('application', [
            'url_ios' => 'https://ios.example', 'url_android' => '', 'url_fallback' => 'https://fallback.example',
        ]));
        $this->assertSame('', QrTarget::redirectTarget('application', ['url_ios' => '', 'url_android' => '', 'url_fallback' => '']));
    }

    public function test_email_with_body_but_no_subject_is_well_formed(): void
    {
        $this->assertSame('mailto:a@b.com?body=Hi', QrTarget::redirectTarget('email', ['email' => 'a@b.com', 'subject' => '', 'body' => 'Hi']));
    }

    public function test_email_with_neither_subject_nor_body(): void
    {
        $this->assertSame('mailto:a@b.com', QrTarget::redirectTarget('email', ['email' => 'a@b.com', 'subject' => '', 'body' => '']));
    }

    public function test_crypto_builds_uri_with_amount(): void
    {
        $this->assertSame('btc:abc123?amount=0.5', QrTarget::redirectTarget('crypto', ['currency' => 'BTC', 'address' => 'abc123', 'amount' => '0.5']));
    }
}
