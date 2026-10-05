<?php

return [
    'sites' => [
        'title' => 'Analytics — Sites',
        'back' => 'Back to sites',
        'create' => 'Add site',
        'edit' => 'Edit site',
        'empty' => 'No sites yet.',
        'empty_hint' => 'Add your first site to start tracking.',

        'columns' => [
            'name' => 'Name',
            'domain' => 'Domain',
            'mode' => 'Mode',
        ],

        'form' => [
            'name' => 'Name',
            'domain' => 'Domain',
            'domain_placeholder' => 'example.com',
            'tracking_mode' => 'Tracking mode',
            'consent_mode' => 'Consent mode',
            'consent_signal' => 'Consent signal (optional)',
            'consent_signal_placeholder' => 'e.g. UC_UI (Usercentrics)',
            'consent_signal_hint' => "Set window.<name> = true when consent is granted and dispatch a 'marketix:consent' event on change.",
            'retention_days' => 'Retention (days, optional)',
            'retention_days_hint' => 'Days to retain raw analytics (blank = project default 24 months).',
            'respect_dnt' => 'Respect Do-Not-Track header',
        ],

        'snippet_title' => 'Tracking snippet',
        'snippet_hint' => 'Paste this into the <head> of :domain.',
        'copy' => 'Copy snippet',
        'copied' => 'Copied!',
    ],

    'goals' => [
        'title' => 'Goals — :name',
        'back' => 'Back to analytics',
        'create' => 'Add goal',
        'edit' => 'Edit goal',
        'empty' => 'No goals yet. Add one to track conversions.',

        'columns' => [
            'name' => 'Name',
            'type' => 'Type',
            'match' => 'Match',
        ],

        'form' => [
            'name' => 'Name',
            'type' => 'Type',
            'match_value' => 'Match value',
            'match_value_hint_event' => "Event name, as in marketix('event', 'name').",
            'match_value_hint_page' => 'Path like /danke, or /blog/* to match everything under /blog.',
        ],
    ],

    'dashboard' => [
        'title' => 'Analytics — :name',
        'back' => 'Back to sites',
        'range_today' => 'Today',
        'chart_title' => 'Visitors over time',
        'no_data' => 'No data',
        'map_title' => 'Visitors by country',

        'chart' => [
            'show_table' => 'Show as table',
            'show_chart' => 'Show as chart',
            'date' => 'Date',
            'hour' => 'Time',
            'select_metric' => 'Select at least one metric.',
            'scale_hint' => 'Page views and unique visitors share one scale; each line is scaled to its peak in this period. Exact values in the tooltip or the table.',
        ],

        'live' => [
            'count' => ':count online',
            'hint' => 'Visitors active in the last 5 minutes',
        ],

        'kpi' => [
            'page_views' => 'Page views',
            'unique_visitors' => 'Unique visitors',
            'bounce_rate' => 'Bounce rate',
            'avg_duration' => 'Avg. duration',
            'from_campaigns' => 'From campaigns',
        ],

        'breakdown' => [
            'top_pages' => 'Top pages',
            'top_referrers' => 'Top referrers',
            'countries' => 'Countries',
            'browsers' => 'Browsers',
            'os' => 'Operating systems',
            'devices' => 'Devices',
            'pages' => 'Pages',
            'entry_pages' => 'Entry pages',
            'exit_pages' => 'Exit pages',
            'bounce_sub' => ':rate % bounce rate',
            'sources' => 'Sources',
            'channels' => 'Channels',
            'locations' => 'Locations',
            'languages' => 'Languages',
            'technology' => 'Technology',
        ],

        'channels' => [
            'direct' => 'Direct',
            'organic_search' => 'Organic search',
            'paid' => 'Paid',
            'social' => 'Social',
            'email' => 'Email',
            'referral' => 'Referral',
            'campaign' => 'Other campaigns',
        ],

        'filters' => [
            'title' => 'Filters',
            'apply' => 'Click to filter by this value',
            'remove' => 'Remove filter “:name”',
            'clear' => 'Clear all filters',
            'keys' => [
                'path' => 'Page',
                'entry_path' => 'Entry page',
                'exit_path' => 'Exit page',
                'referer_domain' => 'Referrer',
                'channel' => 'Channel',
                'country_code' => 'Country',
                'browser' => 'Browser',
                'os' => 'Operating system',
                'device' => 'Device',
                'language' => 'Language',
                'utm_source' => 'Source',
                'utm_medium' => 'Medium',
                'utm_campaign' => 'Campaign',
            ],
        ],

        'heatmap' => [
            'title' => 'Activity by weekday & hour',
            'timezone' => 'Time zone: :zone',
            'cell' => ':day, :hours: :count sessions',
            'peak' => 'Busiest: :day, :hours',
        ],

        'campaigns' => [
            'title' => 'Campaigns',
            'hint' => 'Session counts, unique visitors in parentheses. First-touch attribution.',
            'sources' => 'Top sources',
            'mediums' => 'Top mediums',
            'campaigns' => 'Top campaigns',
            'source_medium' => 'Source / medium',
            'terms' => 'Terms',
            'content' => 'Content',
            'no_data' => 'No campaign data',
        ],

        'events' => [
            'title' => 'Events',
            'empty' => 'No events yet',
        ],

        'goals' => [
            'title' => 'Goals',
            'manage' => 'Manage goals',
            'empty' => 'No goals defined.',
            'create_one' => 'Create one',
            'stats' => ':conversions conversions · :visitors visitors ·',
        ],
    ],

    'events' => [
        'page_title' => 'Event — :name',
        'back' => 'Back to analytics',
        'subtitle' => ':total events · :name',
        'empty' => 'No properties recorded for this event.',

        'numeric' => [
            'sum' => 'Sum',
            'avg' => 'Avg',
            'count_suffix' => 'numeric',
        ],
    ],
];
