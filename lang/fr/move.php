<?php

return [
    'action' => 'Déplacer vers un autre projet',
    'title' => 'Déplacer « :name »',
    'project' => 'Projet cible',
    'project_placeholder' => 'Choisir un projet…',
    'domain' => 'Domaine cible',
    'domain_placeholder' => 'Choisir un domaine…',
    'domains_loading' => 'Chargement des domaines…',
    'no_domains' => 'Ce projet n’a pas encore de domaine.',
    'open' => 'Déplacer…',
    'submit' => 'Déplacer',
    'moved' => 'Déplacé vers « :project ».',
    'hints' => [
        'url' => 'Les statistiques, un QR code lié et ses rapports planifiés sont déplacés aussi. Les pixels restent ici et sont dissociés.',
        'qrcode' => 'Son lien court, ses statistiques et ses rapports planifiés sont déplacés aussi. Les pixels restent ici et sont dissociés.',
        'domain' => 'Tous les liens de ce domaine sont déplacés aussi, avec leurs statistiques, QR codes et rapports planifiés. Les pixels restent ici et sont dissociés.',
        'site' => 'Toutes les données analytics, objectifs, entonnoirs et rapports planifiés sont déplacés aussi.',
        'pixel' => 'Le pixel est dissocié de tous les liens de ce projet.',
        'default' => 'Il ne sera ensuite disponible que dans le projet cible.',
    ],
    'errors' => [
        'project' => 'Choisissez un autre projet auquel vous avez accès.',
        'domain' => 'Choisissez un domaine du projet cible.',
        'slug_taken' => 'Le lien court « :slug » est déjà utilisé sur :domain.',
    ],
];
