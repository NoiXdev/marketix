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

        'measurement' => [
            'title' => 'Enhanced measurement',
            'description' => 'Page changes in single-page apps, active time on page and scroll depth are tracked automatically.',
            'outbound_links' => 'Track clicks on outbound links',
            'file_downloads' => 'Track file downloads (PDF, ZIP, Office files …)',
            'search_params' => 'Site search parameters (optional)',
            'search_params_placeholder' => 'q, s, search',
            'search_params_hint' => 'Comma-separated URL parameters to read search terms from. Leave empty to store no search terms – search terms may contain personal data.',
            'not_found_hint' => 'To report 404 pages, call this on your error page:',
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

    'funnels' => [
        'create' => 'Create funnel',
        'edit' => 'Edit funnel',
        'back' => 'Back to conversions',
        'intro' => 'A funnel shows how many sessions on :site go through several steps in a row – and at which step visitors drop off.',
        'delete' => 'Delete funnel',
        'delete_confirm' => 'The funnel will be deleted. The recorded visit data is kept.',

        'form' => [
            'name' => 'Name',
            'name_placeholder' => 'e.g. Checkout',
            'steps' => 'Steps',
            'steps_hint' => 'Counts sessions that reach the steps in this order. Other pages may be visited in between.',
            'step' => 'Step :number',
            'step_type' => 'Type',
            'step_value' => 'Page or event',
            'step_label' => 'Label (optional)',
            'step_label_placeholder' => 'e.g. Viewed cart',
            'add_step' => 'Add step',
            'remove_step' => 'Remove step',
            'move_up' => 'Move up',
            'move_down' => 'Move down',
        ],
    ],

    'dashboard' => [
        'title' => 'Analytics — :name',
        'back' => 'Back to sites',
        'range_today' => 'Today',
        'chart_title' => 'Visitors over time',
        'no_data' => 'No data',
        'map_title' => 'Visitors by country',

        'tabs' => [
            'label' => 'Analytics sections',
            'overview' => [
                'label' => 'Overview',
                'description' => 'The most important numbers of your website at a glance.',
            ],
            'acquisition' => [
                'label' => 'Acquisition',
                'description' => 'Where your visitors come from: channels, referring websites and campaigns.',
                'more' => 'All sources',
            ],
            'behavior' => [
                'label' => 'Behavior',
                'description' => 'What visitors do on your website: pages, interactions, events and active times.',
                'more' => 'All pages',
            ],
            'audience' => [
                'label' => 'Audience',
                'description' => 'Who your visitors are: countries, languages, devices and browsers.',
                'more' => 'More about your audience',
            ],
            'conversions' => [
                'label' => 'Conversions',
                'description' => 'How many visits reach your goals – and where visitors drop off on the way.',
            ],
            'realtime' => [
                'label' => 'Realtime',
                'description' => 'What is happening on your website right now – in the last 30 minutes.',
            ],
            'revenue' => [
                'label' => 'Revenue',
                'description' => 'How much revenue your website generates – and which channels, campaigns and pages contribute.',
            ],
        ],

        'realtime' => [
            'window' => 'Last 30 minutes',
            'auto_refresh' => 'Live – refreshes automatically every :seconds seconds.',
            'active_now' => 'Active right now',
            'active_hint' => 'Visitors active in the last 5 minutes',
            'visitors' => 'Visitors',
            'last_30' => 'in the last 30 minutes',
            'per_minute' => 'Page views per minute',
            'hover_hint' => 'Hover over a bar for details.',
            'minute_detail' => ':when: :views page views · :visitors visitors',
            'minutes_ago' => ':count min ago',
            'now' => 'Now',
            'active_pages' => 'Active pages',
            'feed' => 'Latest activity',
            'event' => 'Event “:name”',
            'nobody' => 'Nobody right now',
            'empty_title' => 'No visitors right now',
            'empty_text' => 'As soon as someone visits your website, it shows up here within a few seconds.',
        ],

        'revenue' => [
            'chart_title' => 'Revenue over time',
            'orders_count' => ':count orders',
            'kpi' => [
                'revenue' => 'Revenue',
                'orders' => 'Orders',
                'average_order_value' => 'Avg. order value',
                'purchase_rate' => 'Purchase rate',
                'revenue_per_visitor' => 'Revenue per visitor',
            ],
            'by_channel' => 'Revenue by channel',
            'by_campaign' => 'Revenue by campaign',
            'by_landing_page' => 'Revenue by landing page',
            'by_country' => 'Revenue by country',
            'other_currencies' => 'All amounts in :currency. Other currencies in this period: :others.',
            'hint' => 'Revenue comes from events named “purchase” with a “value”. It is attributed to the session in which the purchase happened; channel and campaign are that session’s first touch. Purchase rate = sessions with a purchase ÷ all sessions.',
            'empty_title' => 'No revenue tracked yet',
            'empty_text' => 'Send a “purchase” event with the order value after a successful checkout. You will then see revenue, orders and where your buyers come from.',
        ],

        'chart' => [
            'show_table' => 'Show as table',
            'show_chart' => 'Show as chart',
            'week_of' => 'Week of :date',
            'select_metric' => 'Select at least one metric.',
            'scale_hint' => 'Page views and unique visitors share one scale; each line is scaled to its peak in this period. Exact values in the tooltip or the table.',
        ],

        'range' => [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '90d' => 'Last 90 days',
            'month' => 'This month',
            'last_month' => 'Last month',
            'year' => 'This year',
            '12m' => 'Last 12 months',
            'custom' => 'Custom range',
            'from' => 'From',
            'to' => 'To',
            'apply' => 'Apply range',
        ],

        'compare' => [
            'label' => 'Comparison',
            'previous' => 'vs. previous period',
            'year' => 'vs. previous year',
            'none' => 'No comparison',
            'vs_year' => 'vs. previous year',
        ],

        'interval' => [
            'label' => 'Interval',
            'hour' => 'Hour',
            'day' => 'Day',
            'week' => 'Week',
            'month' => 'Month',
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
            'engagement' => 'Avg. :time active · :scroll % scrolled',
            'engagement_time' => 'Avg. :time active',
        ],

        'interactions' => [
            'title' => 'Interactions',
            'outbound' => 'Outbound links',
            'downloads' => 'Downloads',
            'search_title' => 'Search & error pages',
            'searches' => 'Search terms',
            'not_found' => '404 pages',
            'search_disabled_title' => 'Search terms are not enabled',
            'search_disabled_text' => 'Add the URL parameters of your site search (e.g. q) in the site settings under “Enhanced measurement”. You will then see here what your visitors search for.',
            'open_settings' => 'Open site settings',
            'not_found_empty_title' => 'No 404 pages tracked yet',
            'not_found_empty_text' => 'Add this call to your error page. You will then see here which non-existent pages are requested.',
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

        'funnels' => [
            'title' => 'Funnels',
            'create' => 'Create funnel',
            'edit' => 'Edit funnel',
            'summary' => ':entered sessions started · :completed completed',
            'conversion_rate' => 'Completion rate',
            'continued' => ':rate continued',
            'dropped' => ':count dropped off',
            'empty_title' => 'No funnels yet',
            'empty_text' => 'A funnel shows how many visitors get from e.g. a product page via the cart to a purchase – and at which step they drop off.',
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
