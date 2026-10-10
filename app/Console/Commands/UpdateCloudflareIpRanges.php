<?php

namespace App\Console\Commands;

use App\Support\Cloudflare\CloudflareIpRanges;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class UpdateCloudflareIpRanges extends Command
{
    protected $signature = 'marketix:cloudflare:update';

    protected $description = 'Download the IP ranges of Cloudflare\'s edge servers';

    public function handle(CloudflareIpRanges $ranges): int
    {
        try {
            $response = Http::acceptJson()->timeout(30)->get(CloudflareIpRanges::API_URL);
        } catch (Throwable $e) {
            $this->error("Download failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $this->error("Download failed: HTTP {$response->status()}");

            return self::FAILURE;
        }

        $ipv4 = $response->json('result.ipv4_cidrs');
        $ipv6 = $response->json('result.ipv6_cidrs') ?? [];

        if (! is_array($ipv4) || $ipv4 === [] || ! is_array($ipv6)) {
            $this->error('Unexpected response: no IPv4 ranges.');

            return self::FAILURE;
        }

        $new = array_values([...$ipv4, ...$ipv6]);
        $invalid = array_filter($new, fn ($range) => ! CloudflareIpRanges::isCidr($range));
        if ($invalid !== []) {
            $this->error('Unexpected response: invalid range '.json_encode(array_values($invalid)[0]).'.');

            return self::FAILURE;
        }

        $changed = array_diff($new, $ranges->all()) !== [] || array_diff($ranges->all(), $new) !== [];
        $ranges->store($new);

        $this->info(sprintf('Saved %d IPv4 and %d IPv6 ranges%s.', count($ipv4), count($ipv6), $changed ? ' (changed)' : ''));

        return self::SUCCESS;
    }
}
