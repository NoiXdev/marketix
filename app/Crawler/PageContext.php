<?php

namespace App\Crawler;

class PageContext
{
    /**
     * @param  array<string, string>  $securityHeaders  normalised lower-case header name => first value
     */
    public function __construct(
        public string $url,
        public int $statusCode,
        public string $baseHost,
        public bool $robotsBlocked = false,
        public array $securityHeaders = [],
        public string $scheme = 'https',
    ) {}
}
