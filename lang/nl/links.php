<?php

return [
    'title'      => 'Links',
    'create'     => 'Link aanmaken',
    'empty'      => 'Nog geen links',
    'empty_hint' => 'Maak uw eerste korte link om te beginnen.',
    'count'      => ':count links',
    'subtitle'   => 'Beheer uw korte links',
    'back'       => 'Terug naar links',

    'columns' => [
        'slug'    => 'Korte link',
        'target'  => 'Bestemming',
        'status'  => 'Status',
        'clicks'  => 'Klikken',
        'created' => 'Aangemaakt',
    ],

    'status' => [
        'active'   => 'Actief',
        'inactive' => 'Inactief',
    ],

    'expires' => 'Verloopt op :date',

    'copy' => [
        'idle'   => 'Korte link kopiëren',
        'copied' => 'Gekopieerd!',
    ],

    'delete' => [
        'title'   => 'Link verwijderen?',
        'confirm' => '":slug" verwijderen? Dit kan niet ongedaan worden gemaakt.',
    ],

    'actions' => [
        'view_stats' => 'Statistieken bekijken',
        'activate'   => 'Activeren',
        'deactivate' => 'Deactiveren',
        'create_qr'  => 'QR-code aanmaken',
    ],

    'form' => [
        'section_settings' => 'Linkinstellingen',
        'domain'           => 'Domein',
        'domain_select'    => 'Selecteer domein',
        'slug'             => 'Slug',
        'slug_placeholder' => 'mijn-link',
        'target'           => 'Bestemmings-URL',
        'target_placeholder' => 'https://voorbeeld.nl/mijn-lange-link',
        'status'           => 'Status',
        'password'         => 'Wachtwoord',
        'password_placeholder'          => 'optioneel',
        'password_placeholder_existing' => 'Leeg laten om huidig wachtwoord te behouden',
        'expires_at'       => 'Vervaldatum',
        'save_error_title' => 'Opslaan mislukt — los de volgende fouten op:',
    ],

    'pixels' => [
        'section'     => 'Trackingpixels',
        'description' => 'Selecteer pixels om te activeren vóór omleiding. De omleiding wordt met 2 seconden vertraagd om de pixels te laden.',
    ],

    'targeting' => [
        'geo' => [
            'title'       => 'Geo-targeting',
            'description' => 'Bezoekers doorsturen naar een andere URL op basis van hun land of regio.',
            'all_regions' => 'Alle regio\'s',
        ],
        'device' => [
            'title'       => 'Apparaattargeting',
            'description' => 'Bezoekers doorsturen naar een andere URL op basis van hun besturingssysteem of apparaat.',
        ],
        'language' => [
            'title'       => 'Taaltargeting',
            'description' => 'Bezoekers doorsturen naar een andere URL op basis van hun browsertaal.',
        ],
        'ab' => [
            'title'       => 'A/B-testen',
            'description' => 'Verdeel verkeer over meerdere bestemmings-URLs. De standaardlink is altijd inbegrepen. Geo-, apparaat- en taaltargeting hebben voorrang op A/B-rotatie.',
            'default'     => 'Standaard',
            'enter_url'   => 'Voer hierboven een bestemmings-URL in',
            'auto_weight' => 'Automatisch gewicht: :weight',
            'summary'     => ':variants varianten · totaal expliciet gewicht: :total%',
            'summary_auto' => ':variants varianten · totaal expliciet gewicht: :total% · :auto automatisch gewogen',
        ],
    ],

    'show' => [
        'page_title'       => 'Link · :slug',
        'created'          => 'Aangemaakt op :date',
        'qr_code'          => 'QR-code',
        'no_data'          => 'Nog geen gegevens',
        'no_clicks'        => 'Nog geen klikken',
        'clicks_over_time' => 'Klikken in de tijd',
        'last_days'        => '— laatste :days dagen',
        'recent_clicks'    => 'Recente klikken',
        'cards' => [
            'clicks_alltime' => 'Klikken (totaal)',
            'unique_alltime' => 'Uniek (totaal)',
            'clicks_range'   => 'Klikken (laatste :days d)',
            'unique_range'   => 'Uniek (laatste :days d)',
        ],
        'columns' => [
            'when'     => 'Wanneer',
            'location' => 'Locatie',
            'device'   => 'Apparaat',
            'referrer' => 'Verwijzer',
        ],
        'breakdown' => [
            'countries' => 'Landen',
            'cities'    => 'Steden',
            'browsers'  => 'Browsers',
            'os'        => 'Besturingssystemen',
            'referrers' => 'Verwijzers',
        ],
    ],
];
