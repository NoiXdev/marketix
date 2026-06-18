<?php

namespace App\Services;

interface DnsResolver
{
    /**
     * Resolve a hostname to its list of IPv4 addresses.
     *
     * @return string[] Empty array if the host cannot be resolved.
     */
    public function resolveIps(string $host): array;
}
