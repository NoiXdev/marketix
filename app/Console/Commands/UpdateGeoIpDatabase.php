<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateGeoIpDatabase extends Command
{
    public const DOWNLOAD_URL = 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz';

    protected $signature = 'marketix:geoip:update';

    protected $description = 'Download the MaxMind GeoLite2-City database';

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

        $this->info('Downloading GeoLite2-City database…');

        // Stream download to a temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'geoip').'.tar.gz';

        $response = Http::withBasicAuth($accountId, $licenseKey)
            ->withOptions(['sink' => $tmpFile])
            ->timeout(120)
            ->get(self::DOWNLOAD_URL);

        if (! $response->successful()) {
            $this->error("Download failed: HTTP {$response->status()}");
            @unlink($tmpFile);

            return self::FAILURE;
        }

        $this->info('Extracting…');

        $extractDir = sys_get_temp_dir().'/geoip_extract_'.time();
        mkdir($extractDir, 0755, true);

        try {
            $phar = new \PharData($tmpFile);
            $phar->extractTo($extractDir, null, true);
        } catch (\Throwable $e) {
            $this->error("Extraction failed: {$e->getMessage()}");
            @unlink($tmpFile);

            return self::FAILURE;
        }

        // Locate the .mmdb file inside the extracted directory
        $mmdbFiles = glob($extractDir.'/*/*.mmdb');

        if (empty($mmdbFiles)) {
            $this->error('GeoLite2-City.mmdb not found in archive.');
            @unlink($tmpFile);

            return self::FAILURE;
        }

        $destDir = storage_path('app/geoip');
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $dest = $destDir.'/GeoLite2-City.mmdb';
        rename($mmdbFiles[0], $dest);

        // Cleanup
        @unlink($tmpFile);
        $this->deleteDirectory($extractDir);

        $this->info("Database saved to {$dest}");
        $this->info('GeoIP database updated successfully.');

        return self::SUCCESS;
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
