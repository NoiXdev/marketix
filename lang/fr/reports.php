<?php

return [
    'index' => [
        'title' => 'Rapports programmés',
        'subtitle' => 'Automatisez les rapports récurrents pour ce projet',
        'empty' => 'Aucun rapport programmé pour le moment',
        'create' => 'Créer un rapport',
        'columns' => [
            'name' => 'Nom',
            'type' => 'Type',
            'frequency' => 'Fréquence',
            'next_run' => 'Prochaine exécution',
            'active' => 'Actif',
        ],
        'actions' => [
            'edit' => 'Modifier',
            'delete' => 'Supprimer',
            'send_now' => 'Envoyer maintenant',
        ],
        'delete_confirm' => [
            'title' => 'Supprimer le rapport ?',
            'text' => 'Supprimer ":name" ? Cette action est irréversible.',
            'button' => 'Supprimer',
        ],
        'sent_now_flash' => 'Rapport envoyé.',
    ],

    'form' => [
        'create_title' => 'Créer un rapport',
        'edit_title' => 'Modifier le rapport',
        'name' => 'Nom',
        'name_placeholder' => 'ex. Résumé hebdomadaire',
        'type' => 'Type de rapport',
        'subject' => 'Sujet',
        'subject_link' => 'Sélectionner un lien',
        'subject_site' => 'Sélectionner un site',
        'frequency' => 'Fréquence',
        'weekly' => 'Hebdomadaire',
        'monthly' => 'Mensuel',
        'daily' => 'Quotidien',
        'weekday' => 'Jour de la semaine',
        'day_of_month' => 'Jour du mois',
        'period' => 'Période',
        'period_last_7' => '7 derniers jours',
        'period_last_30' => '30 derniers jours',
        'period_last_90' => '90 derniers jours',
        'period_previous_month' => 'Mois précédent',
        'formats' => 'Formats',
        'format_csv' => 'CSV',
        'format_pdf' => 'PDF',
        'recipients' => 'Destinataires',
        'recipients_hint' => 'Séparez plusieurs adresses e-mail par des virgules.',
        'active' => 'Actif',
        'submit_create' => 'Créer un rapport',
        'submit_save' => 'Enregistrer les modifications',
    ],

    'types' => [
        'project_summary' => 'Résumé du projet',
        'link' => 'Lien',
        'site_analytics' => 'Analyse de site',
    ],

    'frequencies' => [
        'daily' => 'Quotidien',
        'weekly' => 'Hebdomadaire',
        'monthly' => 'Mensuel',
    ],

    'email' => [
        'subject' => ':name — :period',
        'heading' => ':name',
        'intro' => 'Voici votre rapport programmé pour :project, couvrant :period.',
        'footer' => 'Ceci est un rapport automatique de Marketix pour :project.',
    ],

    'report' => [
        'total_clicks' => 'Clics totaux',
        'unique_clicks' => 'Clics uniques',
        'visits' => 'Visites',
        'page_views' => 'Pages vues',
        'goals' => 'Objectifs',
        'top_links' => 'Meilleurs liens',
        'top_countries' => 'Meilleurs pays',
        'top_browsers' => 'Meilleurs navigateurs',
        'top_os' => 'Meilleurs systèmes d’exploitation',
        'top_referrers' => 'Meilleurs référents',
        'top_events' => 'Meilleurs événements',
        'trend' => 'Tendance',
    ],
];
