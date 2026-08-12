<?php

namespace App\Crawler;

class PageContext
{
    public function __construct(
        public string $url,
        public int $statusCode,
        public string $baseHost,
        public bool $robotsBlocked = false,
    ) {}
}
