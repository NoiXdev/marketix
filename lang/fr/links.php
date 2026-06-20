<?php

return [
    'title'      => 'Liens',
    'create'     => 'Créer un lien',
    'empty'      => 'Aucun lien pour le moment',
    'empty_hint' => 'Créez votre premier lien court pour commencer.',
    'count'      => ':count liens',
    'subtitle'   => 'Gérez vos liens courts',
    'back'       => 'Retour aux liens',

    'columns' => [
        'slug'    => 'Lien court',
        'target'  => 'Destination',
        'status'  => 'Statut',
        'clicks'  => 'Clics',
        'created' => 'Créé',
    ],

    'status' => [
        'active'   => 'Actif',
        'inactive' => 'Inactif',
    ],

    'expires' => 'Expire le :date',

    'copy' => [
        'idle'   => 'Copier le lien court',
        'copied' => 'Copié !',
    ],

    'delete' => [
        'title'   => 'Supprimer le lien ?',
        'confirm' => 'Supprimer « :slug » ? Cette action est irréversible.',
    ],

    'actions' => [
        'view_stats' => 'Voir les statistiques',
        'activate'   => 'Activer',
        'deactivate' => 'Désactiver',
        'create_qr'  => 'Créer un QR code',
    ],

    'form' => [
        'section_settings' => 'Paramètres du lien',
        'domain'           => 'Domaine',
        'domain_select'    => 'Sélectionner un domaine',
        'slug'             => 'Slug',
        'slug_placeholder' => 'mon-lien',
        'target'           => 'URL de destination',
        'target_placeholder' => 'https://exemple.com/mon-long-lien',
        'status'           => 'Statut',
        'password'         => 'Mot de passe',
        'password_placeholder'          => 'optionnel',
        'password_placeholder_existing' => 'Laisser vide pour conserver le mot de passe actuel',
        'expires_at'       => "Date d'expiration",
        'save_error_title' => "Impossible d'enregistrer — veuillez corriger les erreurs suivantes :",
    ],

    'pixels' => [
        'section'     => 'Pixels de suivi',
        'description' => 'Sélectionnez les pixels à déclencher avant la redirection. La redirection sera retardée de 2 secondes pour permettre le chargement des pixels.',
    ],

    'targeting' => [
        'geo' => [
            'title'       => 'Ciblage géographique',
            'description' => 'Redirigez les visiteurs vers une URL différente selon leur pays ou région.',
            'all_regions' => 'Toutes les régions',
        ],
        'device' => [
            'title'       => 'Ciblage par appareil',
            'description' => 'Redirigez les visiteurs vers une URL différente selon leur système d\'exploitation ou appareil.',
        ],
        'language' => [
            'title'       => 'Ciblage par langue',
            'description' => 'Redirigez les visiteurs vers une URL différente selon la langue de leur navigateur.',
        ],
        'ab' => [
            'title'       => 'Tests A/B',
            'description' => 'Répartissez le trafic sur plusieurs URL de destination. Le lien par défaut est toujours inclus. Le ciblage géographique, par appareil et par langue est prioritaire sur la rotation A/B.',
            'default'     => 'Par défaut',
            'enter_url'   => 'Entrez l\'URL de destination ci-dessus',
            'auto_weight' => 'Poids automatique : :weight',
            'summary'     => ':variants variantes · poids explicite total : :total%',
            'summary_auto' => ':variants variantes · poids explicite total : :total% · :auto pondérées automatiquement',
        ],
    ],

    'show' => [
        'page_title'       => 'Lien · :slug',
        'created'          => 'Créé le :date',
        'qr_code'          => 'QR code',
        'no_data'          => 'Aucune donnée pour le moment',
        'no_clicks'        => 'Aucun clic pour le moment',
        'clicks_over_time' => 'Clics dans le temps',
        'last_days'        => '— :days derniers jours',
        'recent_clicks'    => 'Clics récents',
        'cards' => [
            'clicks_alltime' => 'Clics (total)',
            'unique_alltime' => 'Uniques (total)',
            'clicks_range'   => 'Clics (:days derniers j)',
            'unique_range'   => 'Uniques (:days derniers j)',
        ],
        'columns' => [
            'when'     => 'Quand',
            'location' => 'Localisation',
            'device'   => 'Appareil',
            'referrer' => 'Référent',
        ],
        'breakdown' => [
            'countries' => 'Pays',
            'cities'    => 'Villes',
            'browsers'  => 'Navigateurs',
            'os'        => 'Systèmes d\'exploitation',
            'referrers' => 'Référents',
        ],
    ],
];
