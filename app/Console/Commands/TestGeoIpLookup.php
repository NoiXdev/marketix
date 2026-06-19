<?php

namespace App\Console\Commands;

use App\Services\GeoIpService;
use Illuminate\Console\Command;

class TestGeoIpLookup extends Command
{
    protected $signature = 'marketix:geoip:test {ip : The IP address to look up}';

    protected $description = 'Look up an IP address against the MaxMind GeoLite2 database';

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

        $this->table(
            ['Field', 'Value'],
            collect($geo)->map(fn ($value, $key) => [$key, $value ?? '—'])->values(),
        );

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
