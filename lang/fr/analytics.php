<?php

return [
    'sites' => [
        'title' => 'Analytics — Sites',
        'back' => 'Retour aux sites',
        'create' => 'Ajouter un site',
        'edit' => 'Modifier le site',
        'empty' => 'Aucun site pour le moment.',
        'empty_hint' => 'Ajoutez votre premier site pour commencer le suivi.',

        'columns' => [
            'name' => 'Nom',
            'domain' => 'Domaine',
            'mode' => 'Mode',
        ],

        'form' => [
            'name' => 'Nom',
            'domain' => 'Domaine',
            'domain_placeholder' => 'exemple.com',
            'tracking_mode' => 'Mode de suivi',
            'consent_mode' => 'Mode de consentement',
            'consent_signal' => 'Signal de consentement (optionnel)',
            'consent_signal_placeholder' => 'ex. UC_UI (Usercentrics)',
            'consent_signal_hint' => "Définit window.<name> = true lorsque le consentement est accordé et déclenche un événement 'marketix:consent' lors d'un changement.",
            'retention_days' => 'Conservation (jours, optionnel)',
            'retention_days_hint' => 'Nombre de jours de conservation des données brutes (vide = valeur par défaut du projet, 24 mois).',
            'respect_dnt' => "Respecter l'en-tête Do-Not-Track",
        ],

        'snippet_title' => 'Snippet de suivi',
        'snippet_hint' => 'Collez ceci dans le <head> de :domain.',
        'copy' => "Copier l'extrait",
        'copied' => 'Copié !',
    ],

    'goals' => [
        'title' => 'Objectifs — :name',
        'back' => 'Retour aux analyses',
        'create' => 'Ajouter un objectif',
        'edit' => "Modifier l'objectif",
        'empty' => 'Aucun objectif pour le moment. Ajoutez-en un pour suivre les conversions.',

        'columns' => [
            'name' => 'Nom',
            'type' => 'Type',
            'match' => 'Correspondance',
        ],

        'form' => [
            'name' => 'Nom',
            'type' => 'Type',
            'match_value' => 'Valeur de correspondance',
            'match_value_hint_event' => "Nom de l'événement, comme dans marketix('event', 'name').",
            'match_value_hint_page' => 'Chemin comme /danke, ou /blog/* pour tout ce qui se trouve sous /blog.',
        ],
    ],

    'dashboard' => [
        'title' => 'Analytics — :name',
        'back' => 'Retour aux sites',
        'range_today' => "Aujourd'hui",
        'chart_title' => 'Visiteurs dans le temps',
        'no_data' => 'Aucune donnée',

        'kpi' => [
            'page_views' => 'Pages vues',
            'unique_visitors' => 'Visiteurs uniques',
            'bounce_rate' => 'Taux de rebond',
            'avg_duration' => 'Durée moy.',
            'from_campaigns' => 'Depuis les campagnes',
        ],

        'breakdown' => [
            'top_pages' => 'Pages les plus vues',
            'top_referrers' => 'Meilleurs référents',
            'countries' => 'Pays',
            'browsers' => 'Navigateurs',
            'os' => "Systèmes d'exploitation",
            'devices' => 'Appareils',
        ],

        'campaigns' => [
            'title' => 'Campagnes',
            'hint' => 'Nombre de sessions, visiteurs uniques entre parenthèses. Attribution au premier contact.',
            'sources' => 'Meilleures sources',
            'mediums' => 'Meilleurs supports',
            'campaigns' => 'Meilleures campagnes',
            'source_medium' => 'Source / support',
            'terms' => 'Termes',
            'content' => 'Contenu',
            'no_data' => 'Aucune donnée de campagne',
        ],

        'events' => [
            'title' => 'Événements',
            'empty' => 'Aucun événement pour le moment',
        ],

        'goals' => [
            'title' => 'Objectifs',
            'manage' => 'Gérer les objectifs',
            'empty' => 'Aucun objectif défini.',
            'create_one' => 'En créer un',
            'stats' => ':conversions conversions · :visitors visiteurs ·',
        ],
    ],

    'events' => [
        'page_title' => 'Événement — :name',
        'back' => 'Retour aux analyses',
        'subtitle' => ':total événements · :name',
        'empty' => 'Aucune propriété enregistrée pour cet événement.',

        'numeric' => [
            'sum' => 'Somme',
            'avg' => 'Moy.',
            'count_suffix' => 'numérique',
        ],
    ],
];
