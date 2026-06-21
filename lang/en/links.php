<?php

return [
    'title'    => 'Links',
    'create'   => 'Create link',
    'empty'    => 'No links yet',
    'empty_hint' => 'Create your first short link to get started.',
    'count'    => ':count links',
    'subtitle' => 'Manage your short links',
    'back'     => 'Back to links',

    'columns' => [
        'slug'    => 'Short link',
        'target'  => 'Destination',
        'status'  => 'Status',
        'clicks'  => 'Clicks',
        'created' => 'Created',
    ],

    'status' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
    ],

    'expires' => 'Expires :date',

    'copy' => [
        'idle'   => 'Copy short link',
        'copied' => 'Copied!',
    ],

    'delete' => [
        'title'   => 'Delete link?',
        'confirm' => 'Delete ":slug"? This cannot be undone.',
    ],

    'reset_stats' => [
        'button'   => 'Reset stats',
        'title'    => 'Reset statistics?',
        'confirm'  => 'This permanently deletes all click statistics for ":slug" and cannot be undone. Type the link slug to confirm.',
        'mismatch' => 'Please type ":slug" to confirm.',
    ],

    'actions' => [
        'view_stats' => 'View stats',
        'activate'   => 'Activate',
        'deactivate' => 'Deactivate',
        'create_qr'  => 'Create QR code',
    ],

    'form' => [
        'section_settings' => 'Link settings',
        'domain'           => 'Domain',
        'domain_select'    => 'Select domain',
        'slug'             => 'Slug',
        'slug_placeholder' => 'my-link',
        'target'           => 'Destination URL',
        'target_placeholder' => 'https://example.com/my-long-url',
        'status'           => 'Status',
        'password'         => 'Password',
        'password_placeholder'         => 'optional',
        'password_placeholder_existing' => 'Leave blank to keep current password',
        'expires_at'       => 'Expires at',
        'save_error_title' => "Couldn't save — please fix the following:",
    ],

    'pixels' => [
        'section'     => 'Tracking Pixels',
        'description' => 'Select pixels to fire before redirecting. The redirect will be delayed by 2 seconds to allow pixels to load.',
    ],

    'targeting' => [
        'geo' => [
            'title'       => 'Geo Targeting',
            'description' => 'Redirect visitors to a different URL based on their country or state/region.',
            'all_regions' => 'All regions',
        ],
        'device' => [
            'title'       => 'Device Targeting',
            'description' => 'Redirect visitors to a different URL based on their operating system or device.',
        ],
        'language' => [
            'title'       => 'Language Targeting',
            'description' => 'Redirect visitors to a different URL based on their browser language.',
        ],
        'ab' => [
            'title'       => 'A/B Testing',
            'description' => 'Rotate traffic across multiple destination URLs. The default link is always included. Geo, device, and language targeting take priority over A/B rotation.',
            'default'     => 'Default',
            'enter_url'   => 'Enter destination URL above',
            'auto_weight' => 'Auto weight: :weight',
            'summary'     => ':variants variants · total explicit weight: :total%',
            'summary_auto' => ':variants variants · total explicit weight: :total% · :auto auto-weighted',
        ],
    ],

    'show' => [
        'page_title'      => 'Link · :slug',
        'created'         => 'Created :date',
        'qr_code'         => 'QR code',
        'no_data'         => 'No data yet',
        'no_clicks'       => 'No clicks yet',
        'clicks_over_time' => 'Clicks over time',
        'last_days'        => '— last :days days',
        'recent_clicks'    => 'Recent clicks',
        'cards' => [
            'clicks_alltime'  => 'Clicks (all time)',
            'unique_alltime'  => 'Unique (all time)',
            'clicks_range'    => 'Clicks (last :days d)',
            'unique_range'    => 'Unique (last :days d)',
        ],
        'columns' => [
            'when'     => 'When',
            'location' => 'Location',
            'device'   => 'Device',
            'referrer' => 'Referrer',
        ],
        'breakdown' => [
            'countries'  => 'Countries',
            'cities'     => 'Cities',
            'browsers'   => 'Browsers',
            'os'         => 'Operating systems',
            'referrers'  => 'Referrers',
        ],
    ],
];
