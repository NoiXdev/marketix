<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeCrawlUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail(__('crawler.invalid_url'));

            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail(__('crawler.invalid_url'));

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (! $host || strtolower($host) === 'localhost') {
            $fail(__('crawler.invalid_url'));

            return;
        }

        $ips = @gethostbynamel($host) ?: (filter_var($host, FILTER_VALIDATE_IP) ? [$host] : []);
        if ($ips === []) {
            $fail(__('crawler.unresolvable_url'));

            return;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail(__('crawler.private_url'));

                return;
            }
        }
    }
}
