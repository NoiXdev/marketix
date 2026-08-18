<?php

// Demo mode. Intended for a SEPARATE deployment (own database, own domain)
// with DEMO_MODE=true. When disabled, no demo route, middleware, provider
// hook or validation rule is active and the app behaves exactly as normal.
return [
    'enabled' => (bool) env('DEMO_MODE', false),

    // Credentials of the single shared demo account, seeded by DemoSeeder.
    'email' => (string) env('DEMO_EMAIL', 'demo@marketix.de'),
    'password' => (string) env('DEMO_PASSWORD', 'demo'),

    // Local time of the nightly reset (see routes/console.php).
    'reset_at' => (string) env('DEMO_RESET_AT', '04:00'),

    // Hosts a demo visitor may point a short link or QR code at. The app's
    // own domain is appended at runtime by DemoAllowedTarget, so this list
    // needs no per-environment editing.
    'allowed_target_hosts' => [
        'example.com',
        'example.org',
        'wikipedia.org',
        'github.com',
        'google.com',
        'youtube.com',
        'laravel.com',
        'openstreetmap.org',
    ],

    'crawl' => [
        'max_pages' => 25,
        // Per reset cycle: the crawls table is truncated on reset, so the
        // row count for a host IS the counter.
        'max_per_host' => 5,
        'max_concurrent' => 1,
    ],
];
