<?php

return [
    'privacy' => [
        'title' => 'Data Privacy',
        'subtitle' => 'How :app handles personal data, and what you need for GDPR (DSGVO) compliance.',
        'disclaimer' => 'This page is practical guidance to help you get started — it is not legal advice. For a binding assessment, consult a qualified data-protection professional.',

        'collect' => [
            'heading' => 'What :app collects',
            'intro' => 'When a visitor opens one of your short links or a tracked analytics page, :app records a small set of technical data to produce aggregate statistics:',
            'ip' => 'IP address — never stored in raw form. It is hashed together with the browser and a random daily salt that is deleted after 48 hours, so the resulting visitor identifier cannot be traced back to an IP afterwards.',
            'geo' => 'Approximate location (country, region, city) derived from the IP at the moment of the request.',
            'device' => 'Device type, browser and operating system, parsed from the User-Agent string.',
            'referrer' => 'Referrer domain — the website a visitor arrived from.',
            'utm' => 'Campaign parameters (UTM tags) that are present on the link.',
            'timestamp' => 'The date and time of the click or page view.',
            'engagement' => 'Interactions on tracked pages: active time on page, scroll depth, and clicks on outbound links and file downloads (target address only, without query parameters).',
            'search' => 'Site search terms – only if you explicitly enable this per site.',
        ],

        'modes' => [
            'heading' => 'Tracking modes & Do-Not-Track',
            'cookieless' => 'Cookieless mode (the default): visitors are recognised only through the daily hash described above — no cookies are stored and visitors cannot be followed from one day to the next.',
            'cookie' => 'Cookie mode (optional, per site): a first-party cookie (mx_vid) is stored so returning visitors can be recognised across visits. It is used only when you enable this mode for a site, and only after the visitor’s consent signal is given.',
            'dnt' => 'Do-Not-Track: each site can be set to honour the browser’s “Do Not Track” signal. Visitors who send it are not tracked at all.',
            'links_note' => 'Short-link click statistics are always cookieless and measured server-side — cookie mode applies only to the :app page-analytics feature.',
        ],

        'nocollect' => [
            'heading' => 'What :app does not do',
            'cookies' => 'No third-party cookies and no cross-site tracking. Analytics is cookieless by default; a first-party cookie is used only if you switch a site to cookie mode, and only with consent (see tracking modes above).',
            'raw_ip' => 'Raw IP addresses are never written to the database — there is no IP column.',
            'pii' => 'No names, email addresses or form contents are collected by the click and analytics tracking.',
            'cross' => 'Your analytics data is never sold or shared with third parties for advertising.',
        ],

        'retention' => [
            'heading' => 'Legal basis & retention',
            'basis' => 'Click and analytics processing is usually based on your legitimate interest in measuring reach (Art. 6(1)(f) GDPR), or on visitor consent where your jurisdiction requires it.',
            'stats' => 'Raw click statistics are kept for :stats months and then automatically pruned.',
            'analytics' => 'Raw page-view and session rows are kept for :analytics months (or less if a site sets a shorter period) and then pruned.',
            'aggregates' => 'Aggregated counts and charts may be kept longer; they contain no personal identifiers.',
        ],

        'notice' => [
            'heading' => 'Privacy notice for your website',
            'intro' => 'If you use :app to track links or pages, tell your visitors. You can adapt the text below for your own privacy policy:',
            'snippet' => 'This website uses :app to measure the reach of its links and pages. When you open a tracked link or page, technical data (a hashed IP address, approximate location, browser, operating system and referring website) is processed to generate anonymous statistics. Cookies are only used if you consent to cookie-based measurement, and the data is not used to identify you personally.',
            'copy' => 'Copy notice',
            'copied' => 'Copied',
        ],

        'responsibilities' => [
            'heading' => 'Your responsibilities as controller',
            'intro' => 'When you use :app, you are the data controller for your visitors’ data. In particular you should:',
            'policy' => 'Publish a privacy policy that discloses this analytics processing (you can use the notice above).',
            'basis' => 'Make sure you have a valid legal basis — a legitimate-interest assessment or consent — for your region.',
            'consent' => 'Choose a tracking mode and consent setting that match your legal basis — enable the Do-Not-Track option and cookie consent where your region requires them.',
            'dpa' => 'Have a data processing agreement (DPA / AVV) in place with whoever operates your :app instance, where required.',
            'rights' => 'Handle data-subject requests. Because visitors are only identified by a daily-rotating, unlinkable hash, most stored data cannot be tied back to an individual.',
        ],
    ],
];
