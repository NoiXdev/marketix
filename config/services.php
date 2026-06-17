<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'maxmind' => [
        'license_key' => env('MAXMIND_LICENSE_KEY'),
    ],

    'traefik' => [
        // File the dynamic config generator writes; Traefik watches this path.
        'dynamic_file' => env('TRAEFIK_DYNAMIC_FILE', base_path('custom-domains.yml')),
        // Internal URL Traefik uses to reach this app (Octane listens on 8000).
        'app_url' => env('TRAEFIK_APP_URL', 'http://app:8000'),
    ],

];
