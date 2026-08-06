<?php

return [
    'nav' => [
        'back_to_app' => 'Zurück zur App',
        'users' => 'Benutzer',
        'projects' => 'Projekte',
        'mailer' => 'Mailer',
        'branding' => 'Branding',
        'storage' => 'Speicher',
        'activity' => 'Aktivität',
        'horizon' => 'Horizon',
        'documentation' => 'Dokumentation',
    ],
    'common' => [
        'leave_blank_to_keep' => '(leer lassen, um beizubehalten)',
        'secret_set' => '•••••••• gesetzt',
        'role' => 'Rolle',
    ],
    'activity' => [
        'title' => 'Aktivitätsprotokoll',
        'filters' => [
            'all_types' => 'Alle Typen',
            'all_projects' => 'Alle Projekte',
            'causer_placeholder' => 'Name/E-Mail des Auslösers',
        ],
    ],
    'projects' => [
        'title' => 'Projekte',
        'add' => 'Projekt hinzufügen',
        'search_placeholder' => 'Projekte durchsuchen…',
        'columns' => [
            'name' => 'Name',
            'members' => 'Mitglieder',
        ],
        'actions' => [
            'open' => 'Projekt öffnen',
        ],
        'fields' => [
            'name' => 'Name',
            'locked' => 'Gesperrt',
        ],
        'create' => [
            'title' => 'Projekt hinzufügen',
        ],
        'edit' => [
            'title' => 'Projekt bearbeiten',
        ],
        'members' => [
            'title' => 'Mitglieder',
            'empty' => 'Noch keine Mitglieder.',
            'assign_label' => 'Benutzer zuweisen',
            'select_user_placeholder' => 'Benutzer auswählen…',
            'assign_button' => 'Zuweisen',
            'remove_confirm' => [
                'title' => 'Mitglied entfernen?',
                'text' => ':name aus diesem Projekt entfernen?',
                'confirm' => 'Entfernen',
            ],
        ],
        'delete_confirm' => [
            'title' => 'Projekt löschen?',
            'text' => '":name" löschen?',
        ],
    ],
    'users' => [
        'title' => 'Benutzer',
        'add' => 'Benutzer hinzufügen',
        'search_placeholder' => 'Name oder E-Mail durchsuchen…',
        'columns' => [
            'name' => 'Name',
            'email' => 'E-Mail',
            'projects' => 'Projekte',
        ],
        'fields' => [
            'name' => 'Name',
            'email' => 'E-Mail',
            'password' => 'Passwort',
            'new_password_hint' => 'Neues Passwort (leer lassen, um es beizubehalten)',
            'super_admin' => 'Super-Admin',
            'force_password_change' => 'Passwortänderung bei nächster Anmeldung erzwingen',
        ],
        'sections' => [
            'account' => 'Konto',
            'security' => 'Sicherheitsaktionen',
        ],
        'security' => [
            'reset_description' => 'Senden Sie diesem Benutzer eine E-Mail mit einem Link zum Zurücksetzen des Passworts.',
            'send_reset' => 'Passwort-Reset senden',
        ],
        'memberships' => [
            'title' => 'Projekt-Mitgliedschaften',
            'empty' => 'Noch keine Projekt-Mitgliedschaft.',
            'add_label' => 'Zu Projekt hinzufügen',
            'select_project_placeholder' => 'Projekt auswählen…',
            'remove_confirm' => [
                'title' => 'Aus Projekt entfernen?',
                'text' => ':name aus :project entfernen?',
                'confirm' => 'Entfernen',
            ],
        ],
        'create' => [
            'title' => 'Benutzer hinzufügen',
        ],
        'edit' => [
            'title' => 'Benutzer bearbeiten',
        ],
        'delete_confirm' => [
            'title' => 'Benutzer löschen?',
            'text' => '":name" löschen? Dies kann nicht widerrufen werden.',
        ],
    ],
    'branding' => [
        'title' => 'Branding',
        'fields' => [
            'app_name' => 'Anwendungsname',
            'app_name_hint' => 'Leer lassen, um den Standard ("Marketix") zu verwenden.',
            'logo_light' => 'Logo (helles Design)',
            'logo_light_hint' => 'Wird auf hellen Hintergründen angezeigt.',
            'logo_dark' => 'Logo (dunkles Design)',
            'logo_dark_hint' => 'Wird auf dunklen Hintergründen angezeigt.',
            'logo_email' => 'E-Mail-/PDF-Logo',
            'logo_email_hint' => 'Wird in E-Mails und PDF-Berichten verwendet.',
            'favicon' => 'Favicon',
            'favicon_hint' => '.ico, .png, .jpg.',
        ],
        'remove_current' => 'Aktuelles entfernen',
    ],
    'mailer' => [
        'title' => 'Mailer-Einstellungen',
        'fields' => [
            'default_mailer' => 'Aktiver Mailer',
            'from_address' => 'Absenderadresse',
            'from_name' => 'Absendername',
            'postal_url' => 'Postal-Server-URL',
            'postal_key' => 'API-Schlüssel',
            'smtp_host' => 'Host',
            'smtp_port' => 'Port',
            'smtp_username' => 'Benutzername',
            'smtp_password' => 'Passwort',
            'smtp_scheme' => 'Verschlüsselung (z. B. tls)',
        ],
        'options' => [
            'postal' => 'Postal',
            'smtp' => 'SMTP',
            'log' => 'Protokoll (kein Versand)',
        ],
        'test' => [
            'title' => 'Test-E-Mail senden',
            'recipient_label' => 'Empfänger (standardmäßig Ihre Adresse)',
            'send_button' => 'Test senden',
        ],
    ],
    'storage' => [
        'title' => 'Speicher-Einstellungen',
        'driver_changed_warning' => 'Bestehende Dateien (Logos, Favicons) bleiben auf dem bisherigen Speicher und müssen ggf. erneut hochgeladen werden. Neue Uploads verwenden den ausgewählten Speicher.',
        'fields' => [
            'driver' => 'Speicher-Backend',
            's3_key' => 'Zugriffsschlüssel-ID',
            's3_secret' => 'Geheimer Zugriffsschlüssel',
            's3_region' => 'Region',
            's3_bucket' => 'Bucket',
            's3_endpoint' => 'Endpunkt',
            's3_endpoint_hint' => 'Leer lassen für AWS; ausfüllen für Cloudflare R2, MinIO, DigitalOcean Spaces, Hetzner.',
            's3_use_path_style' => 'Path-Style-Endpunkt verwenden',
        ],
        'options' => [
            'local' => 'Lokaler Speicher',
            's3' => 'S3-kompatibel',
        ],
        'test_connection' => 'Verbindung testen',
    ],
];
