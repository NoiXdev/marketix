<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Restricts where a demo visitor may point a short link or QR code. Short
 * links resolve publicly on the demo domain, so an unrestricted target would
 * let anyone cloak a phishing destination behind it.
 *
 * Non-http(s) URIs (mailto:, tel:, bitcoin: …) pass through untouched, so the
 * rule is safe to apply to QR targets of any type.
 */
class DemoAllowedTarget implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Protocol-relative URLs (//example.com) inherit the scheme from the current page.
        // In a browser, //evil.test resolves to https://evil.test on a secure page.
        // Treat them as http(s) URLs and validate the host.
        $isProtocolRelative = str_starts_with($value, '//');

        if ($isProtocolRelative) {
            // For protocol-relative URLs, extract the host directly.
            $host = strtolower((string) parse_url($value, PHP_URL_HOST));

            if ($host === '' || ! $this->isAllowed($host)) {
                $fail(__('demo.target_not_allowed', ['hosts' => implode(', ', $this->allowedHosts())]));
            }

            return;
        }

        // Detect malformed URLs where parse_url() completely fails.
        // Examples: "http:///" "http://@/x" "http:///path"
        if (parse_url($value) === false) {
            $fail(__('demo.target_not_allowed', ['hosts' => implode(', ', $this->allowedHosts())]));

            return;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return;
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));

        if ($host === '' || ! $this->isAllowed($host)) {
            $fail(__('demo.target_not_allowed', ['hosts' => implode(', ', $this->allowedHosts())]));
        }
    }

    /** @return list<string> */
    private function allowedHosts(): array
    {
        $hosts = array_map('strtolower', (array) config('demo.allowed_target_hosts', []));
        $own = strtolower((string) config('app.domain'));

        if ($own !== '') {
            $hosts[] = $own;
        }

        return array_values(array_unique($hosts));
    }

    private function isAllowed(string $host): bool
    {
        foreach ($this->allowedHosts() as $allowed) {
            // Exact match, or a real subdomain — the leading dot prevents
            // "notexample.com" from matching "example.com".
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
