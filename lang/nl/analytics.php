<?php

return [
    'sites' => [
        'title' => 'Analytics — Sites',
        'back' => 'Terug naar sites',
        'create' => 'Site toevoegen',
        'edit' => 'Site bewerken',
        'empty' => 'Nog geen sites.',
        'empty_hint' => 'Voeg je eerste site toe om te beginnen met tracken.',

        'columns' => [
            'name' => 'Naam',
            'domain' => 'Domein',
            'mode' => 'Modus',
        ],

        'form' => [
            'name' => 'Naam',
            'domain' => 'Domein',
            'domain_placeholder' => 'voorbeeld.nl',
            'tracking_mode' => 'Trackingmodus',
            'consent_mode' => 'Consentmodus',
            'consent_signal' => 'Consentsignaal (optioneel)',
            'consent_signal_placeholder' => 'bijv. UC_UI (Usercentrics)',
            'consent_signal_hint' => "Zet window.<name> = true wanneer toestemming is verleend en activeert bij wijziging een 'marketix:consent'-event.",
            'retention_days' => 'Bewaartermijn (dagen, optioneel)',
            'retention_days_hint' => 'Aantal dagen dat ruwe analysegegevens worden bewaard (leeg = projectstandaard 24 maanden).',
            'respect_dnt' => 'Do-Not-Track-header respecteren',
        ],

        'snippet_title' => 'Trackingsnippet',
        'snippet_hint' => 'Plak dit in de <head> van :domain.',
        'copy' => 'Snippet kopiëren',
        'copied' => 'Gekopieerd!',
    ],

    'goals' => [
        'title' => 'Doelen — :name',
        'back' => 'Terug naar analytics',
        'create' => 'Doel toevoegen',
        'edit' => 'Doel bewerken',
        'empty' => 'Nog geen doelen. Voeg er een toe om conversies te volgen.',

        'columns' => [
            'name' => 'Naam',
            'type' => 'Type',
            'match' => 'Match',
        ],

        'form' => [
            'name' => 'Naam',
            'type' => 'Type',
            'match_value' => 'Matchwaarde',
            'match_value_hint_event' => "Eventnaam, zoals in marketix('event', 'name').",
            'match_value_hint_page' => 'Pad zoals /danke, of /blog/* om alles onder /blog te matchen.',
        ],
    ],

    'dashboard' => [
        'title' => 'Analytics — :name',
        'back' => 'Terug naar sites',
        'range_today' => 'Vandaag',
        'chart_title' => 'Bezoekers over tijd',
        'no_data' => 'Geen gegevens',
        'map_title' => 'Bezoekers per land',

        'chart' => [
            'show_table' => 'Als tabel tonen',
            'show_chart' => 'Als grafiek tonen',
            'date' => 'Datum',
            'hour' => 'Tijd',
            'select_metric' => 'Selecteer minstens één metriek.',
            'scale_hint' => 'Paginaweergaven en unieke bezoekers delen één schaal; elke lijn is geschaald naar zijn piek in deze periode. Exacte waarden in de tooltip of de tabel.',
        ],

        'live' => [
            'count' => ':count online',
            'hint' => 'Bezoekers actief in de laatste 5 minuten',
        ],

        'kpi' => [
            'page_views' => 'Paginaweergaven',
            'unique_visitors' => 'Unieke bezoekers',
            'bounce_rate' => 'Bouncepercentage',
            'avg_duration' => 'Gem. duur',
            'from_campaigns' => 'Uit campagnes',
        ],

        'breakdown' => [
            'top_pages' => "Toppagina's",
            'top_referrers' => 'Topverwijzers',
            'countries' => 'Landen',
            'browsers' => 'Browsers',
            'os' => 'Besturingssystemen',
            'devices' => 'Apparaten',
            'pages' => "Pagina's",
            'entry_pages' => "Instappagina's",
            'exit_pages' => "Uitstappagina's",
            'bounce_sub' => ':rate % bounce',
            'sources' => 'Bronnen',
            'channels' => 'Kanalen',
            'locations' => 'Locaties',
            'languages' => 'Talen',
            'technology' => 'Technologie',
        ],

        'channels' => [
            'direct' => 'Direct',
            'organic_search' => 'Organisch zoeken',
            'paid' => 'Betaald',
            'social' => 'Sociale media',
            'email' => 'E-mail',
            'referral' => 'Verwijzingen',
            'campaign' => 'Overige campagnes',
        ],

        'filters' => [
            'title' => 'Filters',
            'apply' => 'Klik om op deze waarde te filteren',
            'remove' => 'Filter “:name” verwijderen',
            'clear' => 'Alle filters wissen',
            'keys' => [
                'path' => 'Pagina',
                'entry_path' => 'Instappagina',
                'exit_path' => 'Uitstappagina',
                'referer_domain' => 'Verwijzer',
                'channel' => 'Kanaal',
                'country_code' => 'Land',
                'browser' => 'Browser',
                'os' => 'Besturingssysteem',
                'device' => 'Apparaat',
                'language' => 'Taal',
                'utm_source' => 'Bron',
                'utm_medium' => 'Medium',
                'utm_campaign' => 'Campagne',
            ],
        ],

        'heatmap' => [
            'title' => 'Activiteit per weekdag en uur',
            'timezone' => 'Tijdzone: :zone',
            'cell' => ':day, :hours: :count sessies',
            'peak' => 'Drukst: :day, :hours',
            'less' => 'Minder',
            'more' => 'Meer',
        ],

        'campaigns' => [
            'title' => 'Campagnes',
            'hint' => 'Sessieaantallen, unieke bezoekers tussen haakjes. First-touch-attributie.',
            'sources' => 'Topbronnen',
            'mediums' => 'Topmedia',
            'campaigns' => 'Topcampagnes',
            'source_medium' => 'Bron / medium',
            'terms' => 'Zoektermen',
            'content' => 'Inhoud',
            'no_data' => 'Geen campagnegegevens',
        ],

        'events' => [
            'title' => 'Events',
            'empty' => 'Nog geen events',
        ],

        'goals' => [
            'title' => 'Doelen',
            'manage' => 'Doelen beheren',
            'empty' => 'Geen doelen gedefinieerd.',
            'create_one' => 'Maak er een aan',
            'stats' => ':conversions conversies · :visitors bezoekers ·',
        ],
    ],

    'events' => [
        'page_title' => 'Event — :name',
        'back' => 'Terug naar analytics',
        'subtitle' => ':total events · :name',
        'empty' => 'Geen eigenschappen geregistreerd voor dit event.',

        'numeric' => [
            'sum' => 'Som',
            'avg' => 'Gem.',
            'count_suffix' => 'numeriek',
        ],
    ],
];
