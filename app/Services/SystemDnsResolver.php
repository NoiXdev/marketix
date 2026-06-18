<?php

namespace App\Services;

class SystemDnsResolver implements DnsResolver
{
    public function resolveIps(string $host): array
    {
        $ips = gethostbynamel($host);

        return $ips === false ? [] : $ips;
    }
}
