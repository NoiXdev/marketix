<?php

namespace App\Services;

use App\Support\DataCenterNetworks;
use GeoIp2\Database\Reader;

class GeoIpService
{
    private ?Reader $reader = null;

    private ?Reader $asnReader = null;

    public function __construct()
    {
        $path = storage_path('app/geoip/GeoLite2-City.mmdb');

        if (file_exists($path)) {
            $this->reader = new Reader($path);
        }

        $asnPath = storage_path('app/geoip/GeoLite2-ASN.mmdb');

        if (file_exists($asnPath)) {
            $this->asnReader = new Reader($asnPath);
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

    /**
     * The autonomous system (network operator) the IP belongs to.
     *
     * @return array{asn: int, organization: ?string}|null
     */
    public function network(string $ip): ?array
    {
        if (! $this->asnReader) {
            return null;
        }

        try {
            $record = $this->asnReader->asn($ip);
        } catch (\Exception) {
            return null;
        }

        if ($record->autonomousSystemNumber === null) {
            return null;
        }

        return [
            'asn' => $record->autonomousSystemNumber,
            'organization' => $record->autonomousSystemOrganization,
        ];
    }

    public function fromDataCenter(string $ip): bool
    {
        $network = $this->network($ip);

        return $network !== null && DataCenterNetworks::contains($network['asn'], $network['organization']);
    }

    public function isAvailable(): bool
    {
        return $this->reader !== null;
    }

    public function detectsDataCenters(): bool
    {
        return $this->asnReader !== null;
    }
}
