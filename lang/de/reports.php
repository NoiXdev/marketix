<?php

return [
    'index' => [
        'title' => 'Geplante Berichte',
        'subtitle' => 'Automatisieren Sie wiederkehrende Berichte für dieses Projekt',
        'empty' => 'Noch keine geplanten Berichte',
        'create' => 'Bericht erstellen',
        'columns' => [
            'name' => 'Name',
            'type' => 'Typ',
            'frequency' => 'Häufigkeit',
            'next_run' => 'Nächster Lauf',
            'active' => 'Aktiv',
        ],
        'actions' => [
            'edit' => 'Bearbeiten',
            'delete' => 'Löschen',
            'send_now' => 'Jetzt senden',
        ],
        'delete_confirm' => [
            'title' => 'Bericht löschen?',
            'text' => 'Möchten Sie ":name" löschen? Dies kann nicht rückgängig gemacht werden.',
            'button' => 'Löschen',
        ],
        'sent_now_flash' => 'Bericht wurde gesendet.',
    ],

    'form' => [
        'create_title' => 'Bericht erstellen',
        'edit_title' => 'Bericht bearbeiten',
        'name' => 'Name',
        'name_placeholder' => 'z. B. Wöchentliche Zusammenfassung',
        'type' => 'Berichtstyp',
        'subject' => 'Betreff',
        'subject_link' => 'Link auswählen',
        'subject_site' => 'Website auswählen',
        'frequency' => 'Häufigkeit',
        'weekly' => 'Wöchentlich',
        'monthly' => 'Monatlich',
        'daily' => 'Täglich',
        'weekday' => 'Wochentag',
        'day_of_month' => 'Tag des Monats',
        'period' => 'Zeitraum',
        'period_last_7' => 'Letzte 7 Tage',
        'period_last_30' => 'Letzte 30 Tage',
        'period_last_90' => 'Letzte 90 Tage',
        'period_previous_month' => 'Vorheriger Monat',
        'formats' => 'Formate',
        'format_csv' => 'CSV',
        'format_pdf' => 'PDF',
        'recipients' => 'Empfänger',
        'recipients_hint' => 'Trennen Sie mehrere E-Mail-Adressen mit Kommas.',
        'active' => 'Aktiv',
        'submit_create' => 'Bericht erstellen',
        'submit_save' => 'Änderungen speichern',
    ],

    'types' => [
        'project_summary' => 'Projektübersicht',
        'link' => 'Link',
        'site_analytics' => 'Website-Analyse',
    ],

    'frequencies' => [
        'daily' => 'Täglich',
        'weekly' => 'Wöchentlich',
        'monthly' => 'Monatlich',
    ],

    'email' => [
        'subject' => ':name — :period',
        'heading' => ':name',
        'intro' => 'Hier ist Ihr geplanter Bericht für :project für den Zeitraum :period.',
        'footer' => 'Dies ist ein automatischer Bericht von Marketix für :project.',
    ],

    'report' => [
        'total_clicks' => 'Klicks gesamt',
        'unique_clicks' => 'Eindeutige Klicks',
        'visits' => 'Besuche',
        'page_views' => 'Seitenaufrufe',
        'goals' => 'Ziele',
        'top_links' => 'Top-Links',
        'top_countries' => 'Top-Länder',
        'top_browsers' => 'Top-Browser',
        'top_os' => 'Top-Betriebssysteme',
        'top_referrers' => 'Top-Verweise',
        'top_events' => 'Top-Ereignisse',
        'trend' => 'Trend',
    ],
];
