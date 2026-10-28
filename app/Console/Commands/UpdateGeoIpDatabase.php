<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateGeoIpDatabase extends Command
{
    public const DOWNLOAD_URL = 'https://download.maxmind.com/geoip/databases/%s/download?suffix=tar.gz';

    /**
     * City and region for the statistics; the network operator (ASN) to
     * recognise traffic out of data centers as automated.
     */
    public const EDITIONS = ['GeoLite2-City', 'GeoLite2-ASN'];

    protected $signature = 'marketix:geoip:update';

    protected $description = 'Download the MaxMind GeoLite2-City and GeoLite2-ASN databases';

    public function handle(): int
    {
        $accountId = config('services.maxmind.account_id');
        $licenseKey = config('services.maxmind.license_key');

        if (! $accountId) {
            $this->error('MAXMIND_ACCOUNT_ID is not configured in .env');

            return self::FAILURE;
        }

        if (! $licenseKey) {
            $this->error('MAXMIND_LICENSE_KEY is not configured in .env');

            return self::FAILURE;
        }

        // PharData loads the archive into memory; the GeoLite2-City tarball is
        // ~60MB and exhausts the default 128M limit, which kills extraction with
        // an uncatchable fatal error. Raise the limit for this command only.
        ini_set('memory_limit', '512M');

        foreach (self::EDITIONS as $edition) {
            if (! $this->download($edition, $accountId, $licenseKey)) {
                return self::FAILURE;
            }
        }

        $this->info('GeoIP databases updated successfully.');

        return self::SUCCESS;
    }

    private function download(string $edition, string $accountId, string $licenseKey): bool
    {
        $this->info("Downloading {$edition} database…");

        // Stream download to a temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'geoip').'.tar.gz';

        $response = Http::withBasicAuth($accountId, $licenseKey)
            ->withOptions(['sink' => $tmpFile])
            ->timeout(120)
            ->get(sprintf(self::DOWNLOAD_URL, $edition));

        if (! $response->successful()) {
            $this->error("Download failed: HTTP {$response->status()}");
            @unlink($tmpFile);

            return false;
        }

        $this->info('Extracting…');

        $extractDir = sys_get_temp_dir().'/geoip_extract_'.$edition.'_'.time();
        mkdir($extractDir, 0755, true);

        try {
            $phar = new \PharData($tmpFile);
            $phar->extractTo($extractDir, null, true);
        } catch (\Throwable $e) {
            $this->error("Extraction failed: {$e->getMessage()}");
            @unlink($tmpFile);
            $this->deleteDirectory($extractDir);

            return false;
        }

        // Locate the .mmdb file inside the extracted directory
        $mmdbFiles = glob($extractDir.'/*/*.mmdb');

        if (empty($mmdbFiles)) {
            $this->error("{$edition}.mmdb not found in archive.");
            @unlink($tmpFile);
            $this->deleteDirectory($extractDir);

            return false;
        }

        $destDir = storage_path('app/geoip');
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $dest = $destDir.'/'.$edition.'.mmdb';
        rename($mmdbFiles[0], $dest);

        // Cleanup
        @unlink($tmpFile);
        $this->deleteDirectory($extractDir);

        $this->info("Database saved to {$dest}");

        return true;
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
