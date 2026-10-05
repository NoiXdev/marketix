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
        'map_title' => 'Visiteurs par pays',

        'chart' => [
            'show_table' => 'Afficher en tableau',
            'show_chart' => 'Afficher en graphique',
            'date' => 'Date',
            'hour' => 'Heure',
            'select_metric' => 'Sélectionnez au moins une métrique.',
            'scale_hint' => 'Pages vues et visiteurs uniques partagent une échelle ; chaque ligne est mise à l’échelle de son maximum sur la période. Valeurs exactes dans l’infobulle ou le tableau.',
        ],

        'live' => [
            'count' => ':count en ligne',
            'hint' => 'Visiteurs actifs au cours des 5 dernières minutes',
        ],

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
            'pages' => 'Pages',
            'entry_pages' => "Pages d'entrée",
            'exit_pages' => 'Pages de sortie',
            'bounce_sub' => ':rate % de rebond',
            'sources' => 'Sources',
            'channels' => 'Canaux',
            'locations' => 'Localisations',
            'languages' => 'Langues',
            'technology' => 'Technologie',
        ],

        'channels' => [
            'direct' => 'Direct',
            'organic_search' => 'Recherche organique',
            'paid' => 'Payant',
            'social' => 'Réseaux sociaux',
            'email' => 'E-mail',
            'referral' => 'Sites référents',
            'campaign' => 'Autres campagnes',
        ],

        'filters' => [
            'title' => 'Filtres',
            'apply' => 'Cliquer pour filtrer sur cette valeur',
            'remove' => 'Retirer le filtre « :name »',
            'clear' => 'Effacer tous les filtres',
            'keys' => [
                'path' => 'Page',
                'entry_path' => "Page d'entrée",
                'exit_path' => 'Page de sortie',
                'referer_domain' => 'Référent',
                'channel' => 'Canal',
                'country_code' => 'Pays',
                'browser' => 'Navigateur',
                'os' => "Système d'exploitation",
                'device' => 'Appareil',
                'language' => 'Langue',
                'utm_source' => 'Source',
                'utm_medium' => 'Support',
                'utm_campaign' => 'Campagne',
            ],
        ],

        'heatmap' => [
            'title' => 'Activité par jour et heure',
            'timezone' => 'Fuseau horaire : :zone',
            'cell' => ':day, :hours : :count sessions',
            'peak' => 'Pic d’activité : :day, :hours',
            'less' => 'Moins',
            'more' => 'Plus',
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
