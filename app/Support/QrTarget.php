<?php

namespace App\Support;

class QrTarget
{
    /**
     * Resolve a dynamic QR's (type, content) into the URI its backing short
     * link should 302 to. Returns '' for vCard (served as a file, not
     * redirected) and for unknown/empty content.
     *
     * @param  array<string, string>  $content
     */
    public static function redirectTarget(string $type, array $content): string
    {
        return match ($type) {
            'link' => $content['url'] ?? '',
            'email' => ($content['email'] ?? '') === '' ? '' :
                'mailto:'.$content['email'].self::mailtoParams($content),
            'phone' => ($content['phone'] ?? '') === '' ? '' : 'tel:'.$content['phone'],
            'sms' => ($content['phone'] ?? '') === '' ? '' :
                'sms:'.$content['phone'].
                (! empty($content['message']) ? '?body='.rawurlencode($content['message']) : ''),
            'whatsapp' => ($content['phone'] ?? '') === '' ? '' :
                'https://wa.me/'.preg_replace('/[^0-9]/', '', $content['phone']).
                (! empty($content['message']) ? '?text='.rawurlencode($content['message']) : ''),
            'crypto' => ($content['address'] ?? '') === '' ? '' :
                strtolower($content['currency'] ?? 'bitcoin').':'.$content['address'].
                (! empty($content['amount']) ? '?amount='.$content['amount'] : ''),
            // First non-empty of fallback → android → ios (mirrors the JS `||` chain;
            // the frontend seeds these as empty strings, so `??` would not fall through).
            'application' => ($content['url_fallback'] ?? '') ?: (($content['url_android'] ?? '') ?: ($content['url_ios'] ?? '')),
            'file' => $content['file_url'] ?? '',
            default => '',
        };
    }

    /**
     * Build the mailto query ('?subject=...&body=...') from whichever of
     * subject/body are present. Returns '' when neither is set. Uses RFC3986
     * encoding so spaces become %20 (matching the frontend).
     *
     * @param  array<string, string>  $content
     */
    private static function mailtoParams(array $content): string
    {
        $params = array_filter([
            'subject' => $content['subject'] ?? '',
            'body' => $content['body'] ?? '',
        ], static fn ($v) => $v !== '');

        return $params === [] ? '' : '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
