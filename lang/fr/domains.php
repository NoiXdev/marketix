<?php

return [
    'title' => 'Domaines',
    'subtitle' => 'Gérez les domaines personnalisés de ce projet',
    'create' => 'Ajouter un domaine',
    'empty' => 'Aucun domaine',
    'empty_hint' => "Ajoutez un domaine personnalisé pour l'utiliser avec vos liens.",
    'columns' => [
        'domain' => 'Domaine',
        'root_redirect' => 'Redirection racine',
        'not_found_redirect' => 'Redirection 404',
        'status' => 'Statut',
    ],
    'status' => [
        'dns' => 'DNS',
        'reachable' => 'Accessible',
        'ssl' => 'SSL',
    ],
    'never_checked' => 'Jamais vérifié',
    'actions' => [
        'check' => 'Vérifier le statut',
    ],
    'delete' => [
        'title' => 'Supprimer le domaine ?',
        'confirm' => 'Supprimer « :name » ? Cette action est irréversible.',
    ],
];
