<?php

namespace App\Rules;

use App\Crawler\UrlSafety;
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

        $ips = UrlSafety::resolveIps($host);
        if ($ips === []) {
            $fail(__('crawler.unresolvable_url'));

            return;
        }

        if (! UrlSafety::ipsAreSafe($ips)) {
            $fail(__('crawler.private_url'));

            return;
        }
    }
}
