<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Http;
use Throwable;

class DomainStatusChecker
{
    public function __construct(
        private DnsResolver $dns,
        private CertificateReader $certs,
    ) {}

    /**
     * @return array{dns_ok: bool, reachable_ok: bool, ssl_ok: bool, check_details: array}
     */
    public function check(Domain $domain): array
    {
        $details = [];

        return [
            'dns_ok' => $this->checkDns($domain->name, $details),
            'ssl_ok' => $this->checkSsl($domain->name, $details),
            'reachable_ok' => $this->checkReachable($domain->name, $details),
            'check_details' => $details,
        ];
    }

    private function checkDns(string $host, array &$details): bool
    {
        try {
            $domainIps = $this->dns->resolveIps($host);
            $appIps = $this->dns->resolveIps(config('app.domain'));
            $details['dns'] = ['domain_ips' => $domainIps, 'app_ips' => $appIps];

            return count(array_intersect($domainIps, $appIps)) > 0;
        } catch (Throwable $e) {
            $details['dns'] = ['error' => $e->getMessage()];

            return false;
        }
    }

    private function checkSsl(string $host, array &$details): bool
    {
        try {
            $cert = $this->certs->read($host);

            if ($cert === null) {
                $details['ssl'] = ['error' => 'Could not read certificate'];

                return false;
            }

            $notExpired = ($cert['valid_to'] ?? 0) > now()->timestamp;
            $names = array_filter(array_merge([$cert['cn'] ?? ''], $cert['san'] ?? []));
            $covers = $this->certCoversHost($host, $names);
            $details['ssl'] = ['expires_at' => $cert['valid_to'] ?? 0, 'names' => array_values($names)];

            return $notExpired && $covers;
        } catch (Throwable $e) {
            $details['ssl'] = ['error' => $e->getMessage()];

            return false;
        }
    }

    private function checkReachable(string $host, array &$details): bool
    {
        try {
            $ips = $this->dns->resolveIps($host);

            if (empty($ips)) {
                $details['reachable'] = ['error' => 'Refusing to probe private or unresolved address'];

                return false;
            }

            foreach ($ips as $ip) {
                if (! $this->isPublicIp($ip)) {
                    $details['reachable'] = ['error' => 'Refusing to probe private or unresolved address'];

                    return false;
                }
            }

            $response = Http::timeout(5)->withOptions(['allow_redirects' => false])->get("https://{$host}/.well-known/marketix");
            $details['reachable'] = ['status' => $response->status()];

            return $response->ok() && $response->json('app') === 'marketix';
        } catch (Throwable $e) {
            $details['reachable'] = ['error' => $e->getMessage()];

            return false;
        }
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param  string[]  $names
     */
    private function certCoversHost(string $host, array $names): bool
    {
        foreach ($names as $name) {
            if (strcasecmp($name, $host) === 0) {
                return true;
            }

            if (str_starts_with($name, '*.')) {
                $suffix = substr($name, 1); // ".example.com"
                if (
                    str_ends_with(strtolower($host), strtolower($suffix))
                    && substr_count($host, '.') === substr_count($name, '.')
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
