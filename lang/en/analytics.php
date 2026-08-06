<?php

return [
    'sites' => [
        'title' => 'Analytics — Sites',
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
    ],

    'goals' => [
        'title' => 'Goals — :name',
        'back' => '← Analytics',
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
        'back' => '← Sites',
        'range_today' => 'Today',
        'chart_title' => 'Visitors over time',
        'no_data' => 'No data',

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
        'back' => '← Analytics',
        'subtitle' => ':total events · :name',
        'empty' => 'No properties recorded for this event.',

        'numeric' => [
            'sum' => 'Sum',
            'avg' => 'Avg',
            'count_suffix' => 'numeric',
        ],
    ],
];
