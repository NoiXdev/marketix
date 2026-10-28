<?php

namespace App\Console\Commands;

use App\Services\GeoIpService;
use Illuminate\Console\Command;

class TestGeoIpLookup extends Command
{
    protected $signature = 'marketix:geoip:test {ip : The IP address to look up}';

    protected $description = 'Look up an IP address against the MaxMind GeoLite2 databases';

    public function handle(GeoIpService $geoIp): int
    {
        $ip = $this->argument('ip');

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->error("\"{$ip}\" is not a valid IP address.");

            return self::FAILURE;
        }

        if (! $geoIp->isAvailable()) {
            $this->error('GeoIP database is not available. Run `marketix:geoip:update` first.');

            return self::FAILURE;
        }

        $geo = $geoIp->lookup($ip);

        $rows = collect($geo)
            ->reject(fn ($value) => is_array($value))
            ->map(fn ($value, $key) => [$key, $value ?? '—'])
            ->values();

        if ($geoIp->detectsDataCenters()) {
            $network = $geoIp->network($ip);

            $rows->push(['asn', $network['asn'] ?? '—']);
            $rows->push(['network', $network['organization'] ?? '—']);
            $rows->push(['data_center', $geoIp->fromDataCenter($ip) ? 'yes (counted as bot)' : 'no']);
        }

        $this->table(['Field', 'Value'], $rows);

        // GeoLite2 (free) frequently resolves datacenter/VPN/anycast IPs to
        // country level only, so an empty city is expected for those ranges.
        if ($geo['country'] === null) {
            $this->warn('No data found for this IP in the GeoLite2 database.');
        } elseif ($geo['city'] === null) {
            $this->line('<comment>Note:</comment> country resolved but no city — common for datacenter/VPN IPs in the free GeoLite2 dataset.');
        }

        return self::SUCCESS;
    }
}
