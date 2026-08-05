<?php

return [
    'title' => 'Domänen',
    'subtitle' => 'Eigene Domains für dieses Projekt verwalten',
    'create' => 'Domain hinzufügen',
    'empty' => 'Noch keine Domains',
    'empty_hint' => 'Fügen Sie eine eigene Domain hinzu, um sie für Ihre Links zu nutzen.',
    'columns' => [
        'domain' => 'Domain',
        'root_redirect' => 'Root-Weiterleitung',
        'not_found_redirect' => '404-Weiterleitung',
        'status' => 'Status',
    ],
    'status' => [
        'dns' => 'DNS',
        'reachable' => 'Erreichbar',
        'ssl' => 'SSL',
    ],
    'never_checked' => 'Nie geprüft',
    'actions' => [
        'check' => 'Status prüfen',
    ],
    'delete' => [
        'title' => 'Domain löschen?',
        'confirm' => '„:name" löschen? Dies kann nicht rückgängig gemacht werden.',
    ],
];
