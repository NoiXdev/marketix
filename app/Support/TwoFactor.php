<?php

namespace App\Support;

use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactor
{
    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function qrCodeDataUri(string $holder, string $secret): string
    {
        return $this->engine->getQRCodeInline(
            (string) config('app.name'),
            $holder,
            $secret,
        );
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code) !== false;
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn (): string => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();
    }
}
