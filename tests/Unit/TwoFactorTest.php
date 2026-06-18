<?php

namespace Tests\Unit;

use App\Support\TwoFactor;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    private function service(): TwoFactor
    {
        return new TwoFactor(new Google2FA);
    }

    public function test_generates_a_secret(): void
    {
        $secret = $this->service()->generateSecret();

        $this->assertIsString($secret);
        $this->assertGreaterThanOrEqual(16, strlen($secret));
    }

    public function test_verifies_a_valid_code_and_rejects_an_invalid_one(): void
    {
        $service = $this->service();
        $engine = new Google2FA;
        $secret = $service->generateSecret();

        $valid = $engine->getCurrentOtp($secret);

        $this->assertTrue($service->verify($secret, $valid));
        $this->assertFalse($service->verify($secret, '000000'));
    }

    public function test_generates_unique_formatted_recovery_codes(): void
    {
        $codes = $this->service()->generateRecoveryCodes();

        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $code);
        }
    }

    public function test_produces_a_qr_code_data_uri(): void
    {
        $service = $this->service();
        $qr = $service->qrCodeDataUri('alice@example.com', $service->generateSecret());

        $this->assertIsString($qr);
        $this->assertNotEmpty($qr);
    }
}
