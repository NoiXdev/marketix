<?php

namespace Tests\Feature;

use App\Console\Commands\UpdateGeoIpDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateGeoIpDatabaseTest extends TestCase
{
    public function test_fails_when_account_id_is_missing(): void
    {
        Http::fake();
        config([
            'services.maxmind.account_id' => null,
            'services.maxmind.license_key' => 'license-key',
        ]);

        $this->artisan('marketix:geoip:update')
            ->expectsOutputToContain('MAXMIND_ACCOUNT_ID')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_when_license_key_is_missing(): void
    {
        Http::fake();
        config([
            'services.maxmind.account_id' => '123456',
            'services.maxmind.license_key' => null,
        ]);

        $this->artisan('marketix:geoip:update')
            ->expectsOutputToContain('MAXMIND_LICENSE_KEY')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_downloads_with_account_id_and_license_key_as_basic_auth(): void
    {
        Http::fake([
            'download.maxmind.com/*' => Http::response('', 401),
        ]);
        config([
            'services.maxmind.account_id' => '123456',
            'services.maxmind.license_key' => 'license-key',
        ]);

        $this->artisan('marketix:geoip:update')
            ->expectsOutputToContain('Download failed: HTTP 401')
            ->assertFailed();

        Http::assertSent(function (Request $request) {
            return $request->url() === UpdateGeoIpDatabase::DOWNLOAD_URL
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('123456:license-key'));
        });
    }
}
