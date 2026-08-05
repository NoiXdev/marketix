<?php

return [
    'title' => 'Domeinen',
    'subtitle' => 'Beheer aangepaste domeinen voor dit project',
    'create' => 'Domein toevoegen',
    'empty' => 'Nog geen domeinen',
    'empty_hint' => 'Voeg een aangepast domein toe om het voor je links te gebruiken.',
    'columns' => [
        'domain' => 'Domein',
        'root_redirect' => 'Root-omleiding',
        'not_found_redirect' => '404-omleiding',
        'status' => 'Status',
    ],
    'status' => [
        'dns' => 'DNS',
        'reachable' => 'Bereikbaar',
        'ssl' => 'SSL',
    ],
    'never_checked' => 'Nooit gecontroleerd',
    'actions' => [
        'check' => 'Status controleren',
    ],
    'delete' => [
        'title' => 'Domein verwijderen?',
        'confirm' => '":name" verwijderen? Dit kan niet ongedaan worden gemaakt.',
    ],
];
