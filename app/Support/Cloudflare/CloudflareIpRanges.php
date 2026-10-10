<?php

namespace App\Support\Cloudflare;

class CloudflareIpRanges
{
    public const API_URL = 'https://api.cloudflare.com/client/v4/ips';

    /** @return list<string> */
    public function all(): array
    {
        return $this->stored() ?? config('cloudflare.ip_ranges', []);
    }

    /** @param list<string> $ranges */
    public function store(array $ranges): void
    {
        $path = config('cloudflare.ranges_path');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Write and rename so a request never reads a half-written file
        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($tmp, json_encode(array_values($ranges), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($tmp, $path);
    }

    public static function isCidr(mixed $range): bool
    {
        if (! is_string($range) || substr_count($range, '/') !== 1) {
            return false;
        }

        [$ip, $prefix] = explode('/', $range);
        $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 32 : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : null);

        return $max !== null && ctype_digit($prefix) && (int) $prefix <= $max;
    }

    /** @return list<string>|null */
    private function stored(): ?array
    {
        $path = config('cloudflare.ranges_path');
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $ranges = json_decode((string) file_get_contents($path), true);

        return is_array($ranges) && array_is_list($ranges) && $ranges !== [] && array_filter($ranges, fn ($r) => ! self::isCidr($r)) === []
            ? $ranges
            : null;
    }
}
