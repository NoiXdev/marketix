<?php

namespace Tests\Feature;

use App\Console\Commands\UpdateGeoIpDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
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
            return $request->url() === sprintf(UpdateGeoIpDatabase::DOWNLOAD_URL, 'GeoLite2-City')
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('123456:license-key'));
        });
    }

    public function test_installs_the_city_and_the_asn_database(): void
    {
        $storage = sys_get_temp_dir().'/geoip_storage_'.uniqid();
        $this->app->useStoragePath($storage);

        Http::fake([
            'download.maxmind.com/geoip/databases/GeoLite2-City/*' => Http::response($this->archive('GeoLite2-City')),
            'download.maxmind.com/geoip/databases/GeoLite2-ASN/*' => Http::response($this->archive('GeoLite2-ASN')),
        ]);
        config([
            'services.maxmind.account_id' => '123456',
            'services.maxmind.license_key' => 'license-key',
        ]);

        try {
            $this->artisan('marketix:geoip:update')->assertSuccessful();

            $this->assertStringEqualsFile($storage.'/app/geoip/GeoLite2-City.mmdb', 'GeoLite2-City database');
            $this->assertStringEqualsFile($storage.'/app/geoip/GeoLite2-ASN.mmdb', 'GeoLite2-ASN database');
        } finally {
            File::deleteDirectory($storage);
        }
    }

    /** A tarball shaped like MaxMind's: one dated directory holding the .mmdb file. */
    private function archive(string $edition): string
    {
        $dir = sys_get_temp_dir().'/geoip_archive_'.uniqid();
        mkdir($dir);

        try {
            $tar = new \PharData($dir.'/'.$edition.'.tar');
            $tar->addFromString($edition.'_20261027/'.$edition.'.mmdb', $edition.' database');
            $tar->compress(\Phar::GZ);

            return file_get_contents($dir.'/'.$edition.'.tar.gz');
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
