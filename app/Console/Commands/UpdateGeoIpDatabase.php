<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateGeoIpDatabase extends Command
{
    protected $signature = 'geoip:update';

    protected $description = 'Download the MaxMind GeoLite2-City database';

    public function handle(): int
    {
        $licenseKey = config('services.maxmind.license_key');

        if (! $licenseKey) {
            $this->error('MAXMIND_LICENSE_KEY is not configured in .env');

            return self::FAILURE;
        }

        $this->info('Downloading GeoLite2-City database…');

        $downloadUrl = sprintf(
            'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=%s&suffix=tar.gz',
            $licenseKey,
        );

        // Stream download to a temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'geoip').'.tar.gz';

        $response = Http::withOptions(['sink' => $tmpFile])->timeout(120)->get($downloadUrl);

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
        } catch (\Exception $e) {
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
