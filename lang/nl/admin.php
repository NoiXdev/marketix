<?php

return [
    'nav' => [
        'back_to_app' => 'Terug naar de app',
        'users' => 'Gebruikers',
        'projects' => 'Projecten',
        'mailer' => 'Mailer',
        'branding' => 'Branding',
        'storage' => 'Opslag',
        'activity' => 'Activiteit',
        'horizon' => 'Horizon',
        'documentation' => 'Documentatie',
    ],
    'common' => [
        'leave_blank_to_keep' => '(leeg laten om huidige te behouden)',
        'secret_set' => '•••••••• ingesteld',
        'role' => 'Rol',
    ],
    'activity' => [
        'title' => 'Activiteitenlogboek',
        'filters' => [
            'all_types' => 'Alle typen',
            'all_projects' => 'Alle projecten',
            'causer_placeholder' => 'Naam/e-mail van veroorzaker',
        ],
    ],
    'projects' => [
        'title' => 'Projecten',
        'add' => 'Project toevoegen',
        'search_placeholder' => 'Projecten zoeken…',
        'columns' => [
            'name' => 'Naam',
            'members' => 'Leden',
        ],
        'actions' => [
            'open' => 'Project openen',
        ],
        'fields' => [
            'name' => 'Naam',
            'locked' => 'Vergrendeld',
        ],
        'create' => [
            'title' => 'Project toevoegen',
        ],
        'edit' => [
            'title' => 'Project bewerken',
        ],
        'members' => [
            'title' => 'Leden',
            'empty' => 'Nog geen leden.',
            'assign_label' => 'Gebruiker toewijzen',
            'select_user_placeholder' => 'Selecteer een gebruiker…',
            'assign_button' => 'Toewijzen',
            'remove_confirm' => [
                'title' => 'Lid verwijderen?',
                'text' => ':name uit dit project verwijderen?',
                'confirm' => 'Verwijderen',
            ],
        ],
        'delete_confirm' => [
            'title' => 'Project verwijderen?',
            'text' => '":name" verwijderen?',
        ],
    ],
    'users' => [
        'title' => 'Gebruikers',
        'add' => 'Gebruiker toevoegen',
        'search_placeholder' => 'Zoek op naam of e-mail…',
        'columns' => [
            'name' => 'Naam',
            'email' => 'E-mail',
            'projects' => 'Projecten',
        ],
        'fields' => [
            'name' => 'Naam',
            'email' => 'E-mail',
            'password' => 'Wachtwoord',
            'new_password_hint' => 'Nieuw wachtwoord (leeg laten om te behouden)',
            'super_admin' => 'Super-beheerder',
            'force_password_change' => 'Wachtwoordwijziging afdwingen bij volgende aanmelding',
        ],
        'sections' => [
            'account' => 'Account',
            'security' => 'Beveiligingsacties',
        ],
        'security' => [
            'reset_description' => 'Stuur deze gebruiker een e-mail met een link om het wachtwoord opnieuw in te stellen.',
            'send_reset' => 'Wachtwoordreset versturen',
        ],
        'memberships' => [
            'title' => 'Projectlidmaatschappen',
            'empty' => 'Nog geen lid van een project.',
            'add_label' => 'Toevoegen aan project',
            'select_project_placeholder' => 'Selecteer een project…',
            'remove_confirm' => [
                'title' => 'Uit project verwijderen?',
                'text' => ':name uit :project verwijderen?',
                'confirm' => 'Verwijderen',
            ],
        ],
        'create' => [
            'title' => 'Gebruiker toevoegen',
        ],
        'edit' => [
            'title' => 'Gebruiker bewerken',
        ],
        'delete_confirm' => [
            'title' => 'Gebruiker verwijderen?',
            'text' => '":name" verwijderen? Dit kan niet ongedaan worden gemaakt.',
        ],
    ],
    'branding' => [
        'title' => 'Branding',
        'fields' => [
            'app_name' => 'Applicatienaam',
            'app_name_hint' => 'Leeg laten om de standaard ("Marketix") te gebruiken.',
            'logo_light' => 'Logo (lichte modus)',
            'logo_light_hint' => 'Wordt getoond op lichte achtergronden.',
            'logo_dark' => 'Logo (donkere modus)',
            'logo_dark_hint' => 'Wordt getoond op donkere achtergronden.',
            'logo_email' => 'E-mail-/pdf-logo',
            'logo_email_hint' => "Wordt gebruikt in e-mails en pdf-rapporten.",
            'favicon' => 'Favicon',
            'favicon_hint' => '.ico, .png, .jpg.',
        ],
        'remove_current' => 'Huidige verwijderen',
    ],
    'mailer' => [
        'title' => 'Mailerinstellingen',
        'fields' => [
            'default_mailer' => 'Actieve mailer',
            'from_address' => 'Afzenderadres',
            'from_name' => 'Afzendernaam',
            'postal_url' => 'Postal-server-URL',
            'postal_key' => 'API-sleutel',
            'smtp_host' => 'Host',
            'smtp_port' => 'Poort',
            'smtp_username' => 'Gebruikersnaam',
            'smtp_password' => 'Wachtwoord',
            'smtp_scheme' => 'Versleuteling (bv. tls)',
        ],
        'options' => [
            'postal' => 'Postal',
            'smtp' => 'SMTP',
            'log' => 'Logboek (geen verzending)',
        ],
        'test' => [
            'title' => 'Testmail versturen',
            'recipient_label' => 'Ontvanger (standaard uw eigen adres)',
            'send_button' => 'Test versturen',
        ],
    ],
    'storage' => [
        'title' => 'Opslaginstellingen',
        'driver_changed_warning' => 'Bestaande bestanden (logo\'s, favicons) blijven op de vorige opslag staan en moeten mogelijk opnieuw worden geüpload. Nieuwe uploads gebruiken de geselecteerde opslag.',
        'fields' => [
            'driver' => 'Opslagsysteem',
            's3_key' => 'Toegangssleutel-ID',
            's3_secret' => 'Geheime toegangssleutel',
            's3_region' => 'Regio',
            's3_bucket' => 'Bucket',
            's3_endpoint' => 'Endpoint',
            's3_endpoint_hint' => 'Leeg laten voor AWS; invullen voor Cloudflare R2, MinIO, DigitalOcean Spaces, Hetzner.',
            's3_use_path_style' => 'Path-style endpoint gebruiken',
        ],
        'options' => [
            'local' => 'Lokale opslag',
            's3' => 'S3-compatibel',
        ],
        'test_connection' => 'Verbinding testen',
    ],
];
