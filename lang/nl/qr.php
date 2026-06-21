<?php

return [
    'edit' => [
        'confirm' => [
            'title' => 'Wijziging opslaan?',
            'text' => 'Deze wijziging verandert de QR-code-afbeelding. Codes die je al hebt afgedrukt of gedeeld komen niet meer overeen en moeten opnieuw worden gemaakt.',
            'button' => 'Toch opslaan',
        ],
    ],

    'versions' => [
        'title' => 'Versiegeschiedenis',
        'empty' => 'Nog geen versies.',
        'current' => 'Huidig',
        'restore' => 'Herstellen',
        'by' => 'door :name',
        'dynamic' => 'Dynamisch',
        'static' => 'Statisch',
        'restore_confirm' => [
            'title' => 'Deze versie herstellen?',
            'text' => 'Hiermee vervang je de huidige QR door de geselecteerde versie en verandert de QR-afbeelding. Reeds afgedrukte codes moeten opnieuw worden gemaakt. Je huidige versie blijft in de geschiedenis bewaard.',
            'button' => 'Herstellen',
        ],
    ],

    'vcard' => [
        'drop' => 'Sleep een .vcf-bestand hierheen of klik om te uploaden',
        'error' => 'Kon geen contact uit dit bestand lezen',
        'pick' => 'Dit bestand bevat :count contacten — kies er één',
        'extras' => 'Extra velden uit bestand bewaard (:count): :fields',
        'clear' => 'Extra velden wissen',
    ],
];
