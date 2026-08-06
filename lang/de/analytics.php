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

    'dashboard' => [
        'title' => 'Analytics — :name',
        'back' => 'Zurück zu den Sites',
        'range_today' => 'Heute',
        'chart_title' => 'Besucher im Zeitverlauf',
        'no_data' => 'Keine Daten',

        'kpi' => [
            'page_views' => 'Seitenaufrufe',
            'unique_visitors' => 'Unique Visitors',
            'bounce_rate' => 'Absprungrate',
            'avg_duration' => 'Ø Verweildauer',
            'from_campaigns' => 'Aus Kampagnen',
        ],

        'breakdown' => [
            'top_pages' => 'Top-Seiten',
            'top_referrers' => 'Top-Referrer',
            'countries' => 'Länder',
            'browsers' => 'Browser',
            'os' => 'Betriebssysteme',
            'devices' => 'Geräte',
        ],

        'campaigns' => [
            'title' => 'Kampagnen',
            'hint' => 'Sitzungszahlen, Unique Visitors in Klammern. First-Touch-Attribution.',
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
