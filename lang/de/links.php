<?php

return [
    'title' => 'Links',
    'create' => 'Link erstellen',
    'empty' => 'Noch keine Links',
    'empty_hint' => 'Erstellen Sie Ihren ersten Kurzlink, um loszulegen.',
    'count' => ':count Links',
    'subtitle' => 'Verwalten Sie Ihre Kurzlinks',
    'back' => 'Zurück zu den Links',

    'columns' => [
        'slug' => 'Kurzlink',
        'target' => 'Ziel',
        'status' => 'Status',
        'clicks' => 'Klicks',
        'created' => 'Erstellt',
    ],

    'status' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],

    'expires' => 'Läuft ab :date',

    'copy' => [
        'idle' => 'Kurzlink kopieren',
        'copied' => 'Kopiert!',
    ],

    'delete' => [
        'title' => 'Link löschen?',
        'confirm' => '":slug" löschen? Dies kann nicht rückgängig gemacht werden.',
    ],

    'reset_stats' => [
        'button' => 'Statistiken zurücksetzen',
        'title' => 'Statistiken zurücksetzen?',
        'confirm' => 'Dadurch werden alle Klickstatistiken für „:slug" dauerhaft gelöscht und können nicht wiederhergestellt werden. Geben Sie den Link-Slug ein, um zu bestätigen.',
        'mismatch' => 'Bitte geben Sie „:slug" ein, um zu bestätigen.',
    ],

    'actions' => [
        'view_stats' => 'Statistiken ansehen',
        'activate' => 'Aktivieren',
        'deactivate' => 'Deaktivieren',
        'create_qr' => 'QR-Code erstellen',
    ],

    'form' => [
        'section_settings' => 'Link-Einstellungen',
        'domain' => 'Domain',
        'domain_select' => 'Domain auswählen',
        'slug' => 'Slug',
        'slug_placeholder' => 'mein-link',
        'target' => 'Ziel-URL',
        'target_placeholder' => 'https://beispiel.de/mein-langer-link',
        'status' => 'Status',
        'password' => 'Passwort',
        'password_placeholder' => 'optional',
        'password_placeholder_existing' => 'Leer lassen, um das aktuelle Passwort beizubehalten',
        'expires_at' => 'Ablaufdatum',
        'save_error_title' => 'Speichern fehlgeschlagen – bitte beheben Sie folgende Fehler:',
    ],

    'pixels' => [
        'section' => 'Tracking-Pixel',
        'description' => 'Wählen Sie Pixel aus, die vor der Weiterleitung ausgelöst werden. Die Weiterleitung verzögert sich um 2 Sekunden, damit die Pixel laden können.',
    ],

    'targeting' => [
        'geo' => [
            'title' => 'Geo-Targeting',
            'description' => 'Weiterleitung von Besuchern zu einer anderen URL basierend auf ihrem Land oder ihrer Region.',
            'all_regions' => 'Alle Regionen',
        ],
        'device' => [
            'title' => 'Geräte-Targeting',
            'description' => 'Weiterleitung von Besuchern zu einer anderen URL basierend auf ihrem Betriebssystem oder Gerät.',
        ],
        'language' => [
            'title' => 'Sprach-Targeting',
            'description' => 'Weiterleitung von Besuchern zu einer anderen URL basierend auf ihrer Browsersprache.',
        ],
        'ab' => [
            'title' => 'A/B-Tests',
            'description' => 'Verteilen Sie Traffic auf mehrere Ziel-URLs. Der Standard-Link ist immer enthalten. Geo-, Geräte- und Sprach-Targeting haben Vorrang vor der A/B-Rotation.',
            'default' => 'Standard',
            'enter_url' => 'Geben Sie oben die Ziel-URL ein',
            'auto_weight' => 'Automatische Gewichtung: :weight',
            'summary' => ':variants Varianten · explizites Gesamtgewicht: :total%',
            'summary_auto' => ':variants Varianten · explizites Gesamtgewicht: :total% · :auto automatisch gewichtet',
        ],
    ],

    'show' => [
        'page_title' => 'Link · :slug',
        'created' => 'Erstellt am :date',
        'qr_code' => 'QR-Code',
        'no_data' => 'Noch keine Daten',
        'no_clicks' => 'Noch keine Klicks',
        'clicks_over_time' => 'Klicks über Zeit',
        'last_days' => '— letzte :days Tage',
        'recent_clicks' => 'Letzte Klicks',
        'cards' => [
            'clicks_alltime' => 'Klicks (gesamt)',
            'unique_alltime' => 'Eindeutige (gesamt)',
            'clicks_range' => 'Klicks (letzte :days Tage)',
            'unique_range' => 'Eindeutige (letzte :days Tage)',
        ],
        'columns' => [
            'when' => 'Wann',
            'location' => 'Ort',
            'device' => 'Gerät',
            'referrer' => 'Referrer',
        ],
        'breakdown' => [
            'countries' => 'Länder',
            'cities' => 'Städte',
            'browsers' => 'Browser',
            'os' => 'Betriebssysteme',
            'referrers' => 'Referrer',
        ],
    ],
];
