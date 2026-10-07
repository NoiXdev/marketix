<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class TrustCloudflareClientIp
{
    public function handle(Request $request, Closure $next)
    {
        $visitor = $request->headers->get('CF-Connecting-IP');

        if (is_string($visitor) && filter_var($visitor, FILTER_VALIDATE_IP) !== false && $this->fromCloudflare($request)) {
            $request->headers->set('X-Forwarded-For', $visitor);
        }

        return $next($request);
    }

    private function fromCloudflare(Request $request): bool
    {
        $ranges = config('cloudflare.ip_ranges', []);
        $remote = (string) $request->server->get('REMOTE_ADDR', '');

        if (IpUtils::checkIp($remote, $ranges)) {
            return true;
        }

        // The last X-Forwarded-For entry is set by our reverse proxy, so clients cannot forge it
        $forwarded = array_map('trim', explode(',', (string) $request->headers->get('X-Forwarded-For', '')));
        $peer = end($forwarded);

        return $peer !== false && $peer !== '' && IpUtils::checkIp($peer, $ranges);
    }
}
