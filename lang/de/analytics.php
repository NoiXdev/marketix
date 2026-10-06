<?php

return [
    'sites' => [
        'title' => 'Analytics — Websites',
        'back' => 'Zurück zu den Sites',
        'create' => 'Website hinzufügen',
        'edit' => 'Website bearbeiten',
        'empty' => 'Noch keine Websites.',
        'empty_hint' => 'Fügen Sie Ihre erste Website hinzu, um mit dem Tracking zu beginnen.',

        'columns' => [
            'name' => 'Name',
            'domain' => 'Domain',
            'mode' => 'Modus',
        ],

        'form' => [
            'name' => 'Name',
            'domain' => 'Domain',
            'domain_placeholder' => 'beispiel.de',
            'tracking_mode' => 'Tracking-Modus',
            'consent_mode' => 'Consent-Modus',
            'consent_signal' => 'Consent-Signal (optional)',
            'consent_signal_placeholder' => 'z. B. UC_UI (Usercentrics)',
            'consent_signal_hint' => "Setzt window.<name> = true, wenn die Einwilligung erteilt wird, und löst bei Änderung ein 'marketix:consent'-Event aus.",
            'retention_days' => 'Aufbewahrung (Tage, optional)',
            'retention_days_hint' => 'Tage, für die Rohdaten aufbewahrt werden (leer = Projektstandard 24 Monate).',
            'respect_dnt' => 'Do-Not-Track-Header berücksichtigen',
        ],

        'measurement' => [
            'title' => 'Erweiterte Messung',
            'description' => 'Seitenwechsel in Single-Page-Apps, die aktive Verweildauer und die Scrolltiefe werden automatisch erfasst.',
            'outbound_links' => 'Klicks auf externe Links erfassen',
            'file_downloads' => 'Datei-Downloads erfassen (PDF, ZIP, Office-Dateien …)',
            'search_params' => 'Suchparameter der Website-Suche (optional)',
            'search_params_placeholder' => 'q, s, search',
            'search_params_hint' => 'Kommagetrennte URL-Parameter, aus denen Suchbegriffe erfasst werden. Leer lassen, um keine Suchbegriffe zu speichern – Suchbegriffe können personenbezogene Daten enthalten.',
            'not_found_hint' => 'Um 404-Seiten auszuwerten, rufen Sie auf Ihrer Fehlerseite Folgendes auf:',
        ],

        'snippet_title' => 'Tracking-Snippet',
        'snippet_hint' => 'Fügen Sie dies in den <head> von :domain ein.',
        'copy' => 'Snippet kopieren',
        'copied' => 'Kopiert!',
    ],

    'goals' => [
        'title' => 'Ziele — :name',
        'back' => 'Zurück zu Analytics',
        'create' => 'Ziel hinzufügen',
        'edit' => 'Ziel bearbeiten',
        'empty' => 'Noch keine Ziele. Fügen Sie eines hinzu, um Conversions zu verfolgen.',

        'columns' => [
            'name' => 'Name',
            'type' => 'Typ',
            'match' => 'Übereinstimmung',
        ],

        'form' => [
            'name' => 'Name',
            'type' => 'Typ',
            'match_value' => 'Übereinstimmungswert',
            'match_value_hint_event' => "Event-Name, wie in marketix('event', 'name').",
            'match_value_hint_page' => 'Pfad wie /danke, oder /blog/*, um alles unter /blog zu erfassen.',
        ],
    ],

    'funnels' => [
        'create' => 'Funnel erstellen',
        'edit' => 'Funnel bearbeiten',
        'back' => 'Zurück zu den Conversions',
        'intro' => 'Ein Funnel zeigt, wie viele Sitzungen auf :site mehrere Schritte nacheinander durchlaufen – und an welchem Schritt Besucher abspringen.',
        'delete' => 'Funnel löschen',
        'delete_confirm' => 'Der Funnel wird gelöscht. Die erfassten Besuchsdaten bleiben erhalten.',

        'form' => [
            'name' => 'Name',
            'name_placeholder' => 'z. B. Kaufprozess',
            'steps' => 'Schritte',
            'steps_hint' => 'Gezählt werden Sitzungen, die die Schritte in dieser Reihenfolge erreichen. Dazwischen dürfen weitere Seiten liegen.',
            'step' => 'Schritt :number',
            'step_type' => 'Art',
            'step_value' => 'Seite oder Event',
            'step_label' => 'Bezeichnung (optional)',
            'step_label_placeholder' => 'z. B. Warenkorb angesehen',
            'add_step' => 'Schritt hinzufügen',
            'remove_step' => 'Schritt entfernen',
            'move_up' => 'Nach oben verschieben',
            'move_down' => 'Nach unten verschieben',
        ],
    ],

    'dashboard' => [
        'title' => 'Analytics — :name',
        'back' => 'Zurück zu den Sites',
        'range_today' => 'Heute',
        'chart_title' => 'Besucher im Zeitverlauf',
        'no_data' => 'Keine Daten',
        'map_title' => 'Besucher nach Land',

        'tabs' => [
            'label' => 'Analytics-Bereiche',
            'overview' => [
                'label' => 'Übersicht',
                'description' => 'Die wichtigsten Kennzahlen Ihrer Website auf einen Blick.',
            ],
            'acquisition' => [
                'label' => 'Akquisition',
                'description' => 'Woher Ihre Besucher kommen: Kanäle, verweisende Websites und Kampagnen.',
                'more' => 'Alle Quellen',
            ],
            'behavior' => [
                'label' => 'Verhalten',
                'description' => 'Was Besucher auf Ihrer Website tun: Seiten, Interaktionen, Events und aktive Zeiten.',
                'more' => 'Alle Seiten',
            ],
            'audience' => [
                'label' => 'Zielgruppe',
                'description' => 'Wer Ihre Besucher sind: Länder, Sprachen, Geräte und Browser.',
                'more' => 'Mehr zur Zielgruppe',
            ],
            'conversions' => [
                'label' => 'Conversions',
                'description' => 'Wie viele Besuche Ihre Ziele erreichen – und wo Besucher auf dem Weg dorthin abspringen.',
            ],
            'realtime' => [
                'label' => 'Echtzeit',
                'description' => 'Was gerade auf Ihrer Website passiert – in den letzten 30 Minuten.',
            ],
            'revenue' => [
                'label' => 'Umsatz',
                'description' => 'Wie viel Umsatz Ihre Website erzielt – und welche Kanäle, Kampagnen und Seiten dazu beitragen.',
            ],
        ],

        'realtime' => [
            'window' => 'Letzte 30 Minuten',
            'auto_refresh' => 'Live – aktualisiert sich alle :seconds Sekunden automatisch.',
            'active_now' => 'Gerade aktiv',
            'active_hint' => 'Besucher mit Aktivität in den letzten 5 Minuten',
            'visitors' => 'Besucher',
            'last_30' => 'in den letzten 30 Minuten',
            'per_minute' => 'Seitenaufrufe pro Minute',
            'hover_hint' => 'Für Details mit der Maus über einen Balken fahren.',
            'minute_detail' => ':when: :views Seitenaufrufe · :visitors Besucher',
            'minutes_ago' => 'vor :count Min.',
            'now' => 'Jetzt',
            'active_pages' => 'Aktive Seiten',
            'feed' => 'Letzte Aktivität',
            'event' => 'Event „:name“',
            'nobody' => 'Gerade niemand',
            'empty_title' => 'Gerade keine Besucher',
            'empty_text' => 'Sobald jemand Ihre Website besucht, erscheint das hier innerhalb weniger Sekunden.',
        ],

        'revenue' => [
            'chart_title' => 'Umsatz im Zeitverlauf',
            'orders_count' => ':count Bestellungen',
            'kpi' => [
                'revenue' => 'Umsatz',
                'orders' => 'Bestellungen',
                'average_order_value' => 'Ø Bestellwert',
                'purchase_rate' => 'Kaufrate',
                'revenue_per_visitor' => 'Umsatz pro Besucher',
            ],
            'by_channel' => 'Umsatz nach Kanal',
            'by_campaign' => 'Umsatz nach Kampagne',
            'by_landing_page' => 'Umsatz nach Einstiegsseite',
            'by_country' => 'Umsatz nach Land',
            'other_currencies' => 'Alle Beträge in :currency. Weitere Währungen im Zeitraum: :others.',
            'hint' => 'Umsatz stammt aus Events namens „purchase“ mit dem Wert „value“. Er wird der Sitzung zugeordnet, in der gekauft wurde; Kanal und Kampagne sind der erste Kontakt dieser Sitzung. Kaufrate = Sitzungen mit Kauf ÷ alle Sitzungen.',
            'empty_title' => 'Noch keine Umsätze erfasst',
            'empty_text' => 'Senden Sie nach einem erfolgreichen Kauf ein Event „purchase“ mit dem Bestellwert. Danach sehen Sie hier Umsatz, Bestellungen und woher Ihre Käufer kommen.',
        ],

        'chart' => [
            'show_table' => 'Als Tabelle anzeigen',
            'show_chart' => 'Als Diagramm anzeigen',
            'week_of' => 'Woche ab :date',
            'select_metric' => 'Wählen Sie mindestens eine Metrik aus.',
            'comparison' => 'Vergleich',
            'comparison_hint' => 'Gestrichelte Linien zeigen den Vergleichszeitraum.',
            'change' => 'Änderung',
            'current' => 'Aktuell',
            'previous' => 'Vergleich',
            'scale_hint' => 'Seitenaufrufe und eindeutige Besucher teilen sich eine Skala, jede Linie ist auf ihren Höchstwert im Zeitraum skaliert. Genaue Werte im Tooltip oder in der Tabelle.',
        ],

        'range' => [
            'today' => 'Heute',
            'yesterday' => 'Gestern',
            '7d' => 'Letzte 7 Tage',
            '30d' => 'Letzte 30 Tage',
            '90d' => 'Letzte 90 Tage',
            'month' => 'Dieser Monat',
            'last_month' => 'Letzter Monat',
            'year' => 'Dieses Jahr',
            '12m' => 'Letzte 12 Monate',
            'custom' => 'Benutzerdefiniert',
            'from' => 'Von',
            'to' => 'Bis',
            'apply' => 'Zeitraum anwenden',
        ],

        'compare' => [
            'label' => 'Vergleich',
            'previous' => 'vs. Vorperiode',
            'year' => 'vs. Vorjahr',
            'none' => 'Kein Vergleich',
            'vs_year' => 'vs. Vorjahr',
        ],

        'interval' => [
            'label' => 'Intervall',
            'hour' => 'Stunde',
            'day' => 'Tag',
            'week' => 'Woche',
            'month' => 'Monat',
        ],

        'live' => [
            'count' => ':count online',
            'hint' => 'Besucher mit Aktivität in den letzten 5 Minuten',
        ],

        'kpi' => [
            'page_views' => 'Seitenaufrufe',
            'unique_visitors' => 'Eindeutige Besucher',
            'bounce_rate' => 'Absprungrate',
            'bounce_hint' => 'Sitzungen mit nur einem Seitenaufruf',
            'engagement_rate' => 'Engagement-Rate',
            'engagement_hint' => 'Sitzungen über 10 s, mit 2+ Seiten oder einer Conversion',
            'avg_duration' => 'Ø Verweildauer',
            'from_campaigns' => 'Aus Kampagnen',
        ],

        'channel_table' => [
            'channel' => 'Kanal',
            'sessions' => 'Sitzungen',
            'engagement' => 'Engagement',
            'avg_duration' => 'Ø Dauer',
            'hint' => 'Engagement-Rate: Anteil der Sitzungen, die länger als 10 Sekunden dauern, mindestens 2 Seiten umfassen oder eine Conversion auslösen – wie in Google Analytics 4.',
        ],

        'breakdown' => [
            'top_pages' => 'Top-Seiten',
            'top_referrers' => 'Top-Referrer',
            'countries' => 'Länder',
            'browsers' => 'Browser',
            'os' => 'Betriebssysteme',
            'devices' => 'Geräte',
            'pages' => 'Seiten',
            'entry_pages' => 'Einstiegsseiten',
            'exit_pages' => 'Ausstiegsseiten',
            'bounce_sub' => ':rate % Absprungrate',
            'sources' => 'Quellen',
            'channels' => 'Kanäle',
            'locations' => 'Standorte',
            'languages' => 'Sprachen',
            'technology' => 'Technik',
            'engagement' => 'Ø :time aktiv · :scroll % gescrollt',
            'engagement_time' => 'Ø :time aktiv',
        ],

        'interactions' => [
            'title' => 'Interaktionen',
            'outbound' => 'Ausgehende Links',
            'downloads' => 'Downloads',
            'search_title' => 'Suche & Fehlerseiten',
            'searches' => 'Suchbegriffe',
            'not_found' => '404-Seiten',
            'search_disabled_title' => 'Suchbegriffe sind nicht aktiviert',
            'search_disabled_text' => 'Hinterlegen Sie in den Website-Einstellungen unter „Erweiterte Messung“ die URL-Parameter Ihrer Website-Suche, z. B. q. Danach sehen Sie hier, wonach Ihre Besucher suchen.',
            'open_settings' => 'Website-Einstellungen öffnen',
            'not_found_empty_title' => 'Noch keine 404-Seiten erfasst',
            'not_found_empty_text' => 'Fügen Sie diesen Aufruf auf Ihrer Fehlerseite ein. Danach sehen Sie hier, welche nicht existierenden Seiten aufgerufen werden.',
        ],

        'channels' => [
            'direct' => 'Direkt',
            'organic_search' => 'Organische Suche',
            'paid' => 'Bezahlt',
            'social' => 'Social Media',
            'email' => 'E-Mail',
            'referral' => 'Verweise',
            'campaign' => 'Sonstige Kampagnen',
        ],

        'filters' => [
            'title' => 'Filter',
            'apply' => 'Klicken, um danach zu filtern',
            'remove' => 'Filter „:name“ entfernen',
            'clear' => 'Alle Filter entfernen',
            'keys' => [
                'path' => 'Seite',
                'entry_path' => 'Einstiegsseite',
                'exit_path' => 'Ausstiegsseite',
                'referer_domain' => 'Referrer',
                'channel' => 'Kanal',
                'country_code' => 'Land',
                'browser' => 'Browser',
                'os' => 'Betriebssystem',
                'device' => 'Gerät',
                'language' => 'Sprache',
                'utm_source' => 'Quelle',
                'utm_medium' => 'Medium',
                'utm_campaign' => 'Kampagne',
            ],
        ],

        'heatmap' => [
            'title' => 'Aktivität nach Wochentag & Uhrzeit',
            'timezone' => 'Zeitzone: :zone',
            'cell' => ':day, :hours: :count Sitzungen',
            'peak' => 'Am aktivsten: :day, :hours',
        ],

        'campaigns' => [
            'title' => 'Kampagnen',
            'hint' => 'Sitzungszahlen, eindeutige Besucher in Klammern. First-Touch-Attribution.',
            'sources' => 'Top-Quellen',
            'mediums' => 'Top-Medien',
            'campaigns' => 'Top-Kampagnen',
            'source_medium' => 'Quelle / Medium',
            'terms' => 'Begriffe',
            'content' => 'Inhalt',
            'no_data' => 'Keine Kampagnendaten',
        ],

        'events' => [
            'title' => 'Events',
            'empty' => 'Noch keine Events',
        ],

        'funnels' => [
            'title' => 'Funnels',
            'create' => 'Funnel erstellen',
            'edit' => 'Funnel bearbeiten',
            'summary' => ':entered Sitzungen gestartet · :completed abgeschlossen',
            'conversion_rate' => 'Abschlussrate',
            'continued' => ':rate gingen weiter',
            'dropped' => ':count abgesprungen',
            'empty_title' => 'Noch keine Funnels',
            'empty_text' => 'Mit einem Funnel sehen Sie, wie viele Besucher z. B. vom Produkt über den Warenkorb bis zum Kauf kommen – und an welchem Schritt sie abspringen.',
        ],

        'goals' => [
            'title' => 'Ziele',
            'manage' => 'Ziele verwalten',
            'empty' => 'Keine Ziele definiert.',
            'create_one' => 'Erstellen Sie eines',
            'stats' => ':conversions Conversions · :visitors Visitors ·',
        ],
    ],

    'events' => [
        'page_title' => 'Event — :name',
        'back' => 'Zurück zu Analytics',
        'subtitle' => ':total Events · :name',
        'empty' => 'Für dieses Event wurden keine Eigenschaften erfasst.',

        'numeric' => [
            'sum' => 'Summe',
            'avg' => 'Ø',
            'count_suffix' => 'numerisch',
        ],
    ],
];
