<?php

return [
    'sites' => [
        'title' => 'Analytics — Sites',
        'back' => 'Terug naar sites',
        'create' => 'Site toevoegen',
        'delete' => [
            'title' => 'Site verwijderen',
            'confirm' => '„:name” wordt verwijderd en meet vanaf nu geen bezoeken meer. Reeds verzamelde gegevens worden definitief verwijderd zodra de bewaartermijn verloopt.',
            'action' => 'Site verwijderen',
        ],
        'overview' => [
            'subtitle' => 'Alle sites van dit project in één oogopslag – kerncijfers van de laatste 30 dagen.',
            'visitors' => 'Bezoekers',
            'page_views' => 'Paginaweergaven',
            'engagement' => 'Engagement',
            'trend' => 'Bezoekers per dag, laatste 30 dagen',
            'last_seen' => 'Laatste bezoek :time',
            'open' => 'Analytics openen',
            'no_data_title' => 'Nog geen gegevens',
            'no_data_text' => 'Voeg de trackingcode toe om bezoeken te meten.',
            'setup' => 'Trackingcode',
            'add_hint' => 'Nog een website of shop meten',
        ],
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

        'measurement' => [
            'title' => 'Uitgebreide meting',
            'description' => 'Paginawissels in single-page-apps, actieve tijd op de pagina en scrolldiepte worden automatisch gemeten.',
            'outbound_links' => 'Klikken op uitgaande links meten',
            'file_downloads' => 'Bestandsdownloads meten (PDF, ZIP, Office-bestanden …)',
            'search_params' => 'Zoekparameters van de website (optioneel)',
            'search_params_placeholder' => 'q, s, search',
            'search_params_hint' => 'Kommagescheiden URL-parameters waaruit zoektermen worden gelezen. Leeg laten om geen zoektermen op te slaan – zoektermen kunnen persoonsgegevens bevatten.',
            'not_found_hint' => "Om 404-pagina's te meten, roept u dit aan op uw foutpagina:",
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

    'funnels' => [
        'create' => 'Trechter maken',
        'edit' => 'Trechter bewerken',
        'back' => 'Terug naar conversies',
        'intro' => 'Een trechter toont hoeveel sessies op :site meerdere stappen na elkaar doorlopen – en bij welke stap bezoekers afhaken.',
        'delete' => 'Trechter verwijderen',
        'delete_confirm' => 'De trechter wordt verwijderd. De vastgelegde bezoekgegevens blijven behouden.',

        'form' => [
            'name' => 'Naam',
            'name_placeholder' => 'bijv. Aankoopproces',
            'steps' => 'Stappen',
            'steps_hint' => 'Telt sessies die de stappen in deze volgorde bereiken. Daartussen mogen andere pagina’s bezocht worden.',
            'step' => 'Stap :number',
            'step_type' => 'Soort',
            'step_value' => 'Pagina of event',
            'step_label' => 'Label (optioneel)',
            'step_label_placeholder' => 'bijv. Winkelwagen bekeken',
            'add_step' => 'Stap toevoegen',
            'remove_step' => 'Stap verwijderen',
            'move_up' => 'Omhoog',
            'move_down' => 'Omlaag',
        ],
    ],

    'dashboard' => [
        'title' => 'Analytics — :name',
        'back' => 'Terug naar sites',
        'range_today' => 'Vandaag',
        'chart_title' => 'Bezoekers over tijd',
        'no_data' => 'Geen gegevens',
        'map_title' => 'Bezoekers per land',

        'tabs' => [
            'label' => 'Analytics-onderdelen',
            'overview' => [
                'label' => 'Overzicht',
                'description' => 'De belangrijkste cijfers van uw website in één oogopslag.',
            ],
            'acquisition' => [
                'label' => 'Acquisitie',
                'description' => 'Waar uw bezoekers vandaan komen: kanalen, verwijzende websites en campagnes.',
                'more' => 'Alle bronnen',
            ],
            'behavior' => [
                'label' => 'Gedrag',
                'description' => 'Wat bezoekers op uw website doen: pagina’s, interacties, events en actieve tijden.',
                'more' => 'Alle pagina’s',
            ],
            'audience' => [
                'label' => 'Doelgroep',
                'description' => 'Wie uw bezoekers zijn: landen, talen, apparaten en browsers.',
                'more' => 'Meer over uw doelgroep',
            ],
            'conversions' => [
                'label' => 'Conversies',
                'description' => 'Hoeveel bezoeken uw doelen bereiken – en waar bezoekers onderweg afhaken.',
            ],
            'realtime' => [
                'label' => 'Realtime',
                'description' => 'Wat er nu op uw website gebeurt – in de laatste 30 minuten.',
            ],
            'revenue' => [
                'label' => 'Omzet',
                'description' => 'Hoeveel omzet uw website oplevert – en welke kanalen, campagnes en pagina’s daaraan bijdragen.',
            ],
        ],

        'realtime' => [
            'window' => 'Laatste 30 minuten',
            'auto_refresh' => 'Live – ververst automatisch elke :seconds seconden.',
            'active_now' => 'Nu actief',
            'active_hint' => 'Bezoekers actief in de laatste 5 minuten',
            'visitors' => 'Bezoekers',
            'last_30' => 'in de laatste 30 minuten',
            'per_minute' => 'Paginaweergaven per minuut',
            'hover_hint' => 'Beweeg over een balk voor details.',
            'minute_detail' => ':when: :views paginaweergaven · :visitors bezoekers',
            'minutes_ago' => ':count min geleden',
            'now' => 'Nu',
            'active_pages' => 'Actieve pagina’s',
            'feed' => 'Recente activiteit',
            'event' => 'Event „:name”',
            'nobody' => 'Nu niemand',
            'empty_title' => 'Nu geen bezoekers',
            'empty_text' => 'Zodra iemand uw website bezoekt, verschijnt dat hier binnen enkele seconden.',
        ],

        'revenue' => [
            'chart_title' => 'Omzet in de tijd',
            'orders_count' => ':count bestellingen',
            'kpi' => [
                'revenue' => 'Omzet',
                'orders' => 'Bestellingen',
                'average_order_value' => 'Gem. bestelwaarde',
                'purchase_rate' => 'Aankooppercentage',
                'revenue_per_visitor' => 'Omzet per bezoeker',
            ],
            'by_channel' => 'Omzet per kanaal',
            'by_campaign' => 'Omzet per campagne',
            'by_landing_page' => 'Omzet per instappagina',
            'by_country' => 'Omzet per land',
            'other_currencies' => 'Alle bedragen in :currency. Andere valuta in deze periode: :others.',
            'hint' => 'Omzet komt uit events „purchase” met een „value”. Hij wordt toegewezen aan de sessie waarin gekocht werd; kanaal en campagne zijn het eerste contact van die sessie. Aankooppercentage = sessies met aankoop ÷ alle sessies.',
            'empty_title' => 'Nog geen omzet gemeten',
            'empty_text' => 'Stuur na een geslaagde aankoop een event „purchase” met de bestelwaarde. Daarna ziet u hier omzet, bestellingen en waar uw kopers vandaan komen.',
        ],

        'chart' => [
            'show_table' => 'Als tabel tonen',
            'show_chart' => 'Als grafiek tonen',
            'week_of' => 'Week van :date',
            'comparison' => 'Vergelijking',
            'comparison_hint' => 'Stippellijnen tonen de vergelijkingsperiode.',
            'change' => 'Verandering',
            'current' => 'Huidig',
            'previous' => 'Vergelijking',
            'select_metric' =>'Selecteer minstens één metriek.',
            'scale_hint' => 'Paginaweergaven en unieke bezoekers delen één schaal; elke lijn is geschaald naar zijn piek in deze periode. Exacte waarden in de tooltip of de tabel.',
        ],

        'range' => [
            'today' => 'Vandaag',
            'yesterday' => 'Gisteren',
            '7d' => 'Laatste 7 dagen',
            '30d' => 'Laatste 30 dagen',
            '90d' => 'Laatste 90 dagen',
            'month' => 'Deze maand',
            'last_month' => 'Vorige maand',
            'year' => 'Dit jaar',
            '12m' => 'Laatste 12 maanden',
            'custom' => 'Aangepaste periode',
            'from' => 'Van',
            'to' => 'Tot',
            'apply' => 'Periode toepassen',
        ],

        'compare' => [
            'label' => 'Vergelijking',
            'previous' => 't.o.v. vorige periode',
            'year' => 't.o.v. vorig jaar',
            'none' => 'Geen vergelijking',
            'vs_year' => 't.o.v. vorig jaar',
        ],

        'interval' => [
            'label' => 'Interval',
            'hour' => 'Uur',
            'day' => 'Dag',
            'week' => 'Week',
            'month' => 'Maand',
        ],

        'live' => [
            'count' => ':count online',
            'hint' => 'Bezoekers actief in de laatste 5 minuten',
        ],

        'kpi' => [
            'page_views' => 'Paginaweergaven',
            'unique_visitors' => 'Unieke bezoekers',
            'bounce_rate' => 'Bouncepercentage',
            'bounce_hint' => 'Sessies met slechts één paginaweergave',
            'engagement_rate' => 'Engagementpercentage',
            'engagement_hint' => 'Sessies langer dan 10 s, met 2+ pagina’s of een conversie',
            'avg_duration' => 'Gem. duur',
            'from_campaigns' => 'Uit campagnes',
        ],

        'channel_table' => [
            'channel' => 'Kanaal',
            'sessions' => 'Sessies',
            'engagement' => 'Engagement',
            'avg_duration' => 'Gem. duur',
            'hint' => 'Engagementpercentage: aandeel sessies die langer dan 10 seconden duren, minstens 2 pagina’s bevatten of een conversie opleveren – zoals in Google Analytics 4.',
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
            'engagement' => 'Gem. :time actief · :scroll % gescrold',
            'engagement_time' => 'Gem. :time actief',
        ],

        'interactions' => [
            'title' => 'Interacties',
            'outbound' => 'Uitgaande links',
            'downloads' => 'Downloads',
            'search_title' => "Zoeken & foutpagina's",
            'searches' => 'Zoektermen',
            'not_found' => "404-pagina's",
            'search_disabled_title' => 'Zoektermen zijn niet ingeschakeld',
            'search_disabled_text' => 'Vul in de website-instellingen onder „Uitgebreide meting” de URL-parameters van uw zoekfunctie in (bijv. q). Daarna ziet u hier waar uw bezoekers naar zoeken.',
            'open_settings' => 'Website-instellingen openen',
            'not_found_empty_title' => 'Nog geen 404-pagina’s gemeten',
            'not_found_empty_text' => 'Voeg deze aanroep toe aan uw foutpagina. Daarna ziet u hier welke niet-bestaande pagina’s worden opgevraagd.',
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

        'funnels' => [
            'title' => 'Trechters',
            'create' => 'Trechter maken',
            'edit' => 'Trechter bewerken',
            'summary' => ':entered sessies gestart · :completed voltooid',
            'conversion_rate' => 'Voltooiingspercentage',
            'continued' => ':rate ging verder',
            'dropped' => ':count haakte af',
            'empty_title' => 'Nog geen trechters',
            'empty_text' => 'Een trechter toont hoeveel bezoekers bijv. van de productpagina via de winkelwagen tot de aankoop komen – en bij welke stap ze afhaken.',
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
