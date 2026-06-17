<?php

namespace App\Services;

use GeoIp2\Database\Reader;

class GeoIpService
{
    private ?Reader $reader = null;

    public function __construct()
    {
        $path = storage_path('app/geoip/GeoLite2-City.mmdb');

        if (file_exists($path)) {
            $this->reader = new Reader($path);
        }
    }

    public function lookup(string $ip): array
    {
        if (! $this->reader) {
            return [
                'country' => null,
                'city' => null,
                'country_code' => null,
                'subdivision_code' => null,
            ];
        }

        try {
            $record = $this->reader->city($ip);

            return [
                'country' => $record->country->name,
                'city' => $record->city->name,
                'country_code' => $record->country->isoCode,
                'subdivision_code' => $record->mostSpecificSubdivision->isoCode,
            ];
        } catch (\Exception) {
            return [
                'country' => null,
                'city' => null,
                'country_code' => null,
                'subdivision_code' => null,
            ];
        }
    }

    public function isAvailable(): bool
    {
        return $this->reader !== null;
    }
}
