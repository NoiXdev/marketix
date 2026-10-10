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
                'region' => null,
                'subdivision_code' => null,
                'region_names' => [],
                'city_names' => [],
            ];
        }

        try {
            $record = $this->reader->city($ip);

            return [
                'country' => $record->country->name,
                'city' => $record->city->name,
                'country_code' => $record->country->isoCode,
                'region' => $record->mostSpecificSubdivision->name,
                'subdivision_code' => $record->mostSpecificSubdivision->isoCode,
                'region_names' => $record->mostSpecificSubdivision->names,
                'city_names' => $record->city->names,
            ];
        } catch (\Exception) {
            return [
                'country' => null,
                'city' => null,
                'country_code' => null,
                'region' => null,
                'subdivision_code' => null,
                'region_names' => [],
                'city_names' => [],
            ];
        }
    }

    public function isAvailable(): bool
    {
        return $this->reader !== null;
    }
}
