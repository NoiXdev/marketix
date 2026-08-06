<?php

return [
    'nav' => [
        'back_to_app' => "Retour à l'application",
        'users' => 'Utilisateurs',
        'projects' => 'Projets',
        'mailer' => 'Messagerie',
        'branding' => 'Image de marque',
        'storage' => 'Stockage',
        'activity' => 'Activité',
        'horizon' => 'Horizon',
        'documentation' => 'Documentation',
    ],
    'common' => [
        'leave_blank_to_keep' => '(laisser vide pour conserver l\'actuel)',
        'secret_set' => '•••••••• défini',
        'role' => 'Rôle',
    ],
    'activity' => [
        'title' => "Journal d'activité",
        'filters' => [
            'all_types' => 'Tous les types',
            'all_projects' => 'Tous les projets',
            'causer_placeholder' => "Nom/e-mail de l'auteur",
        ],
    ],
    'projects' => [
        'title' => 'Projets',
        'add' => 'Ajouter un projet',
        'search_placeholder' => 'Rechercher des projets…',
        'columns' => [
            'name' => 'Nom',
            'members' => 'Membres',
        ],
        'actions' => [
            'open' => 'Ouvrir le projet',
        ],
        'fields' => [
            'name' => 'Nom',
            'locked' => 'Verrouillé',
        ],
        'create' => [
            'title' => 'Ajouter un projet',
        ],
        'edit' => [
            'title' => 'Modifier le projet',
        ],
        'members' => [
            'title' => 'Membres',
            'empty' => 'Aucun membre.',
            'assign_label' => 'Attribuer à un utilisateur',
            'select_user_placeholder' => 'Sélectionner un utilisateur…',
            'assign_button' => 'Attribuer',
            'remove_confirm' => [
                'title' => 'Retirer le membre ?',
                'text' => 'Retirer :name de ce projet ?',
                'confirm' => 'Retirer',
            ],
        ],
        'delete_confirm' => [
            'title' => 'Supprimer le projet ?',
            'text' => 'Supprimer ":name" ?',
        ],
    ],
    'users' => [
        'title' => 'Utilisateurs',
        'add' => 'Ajouter un utilisateur',
        'search_placeholder' => 'Rechercher par nom ou e-mail…',
        'columns' => [
            'name' => 'Nom',
            'email' => 'E-mail',
            'projects' => 'Projets',
        ],
        'fields' => [
            'name' => 'Nom',
            'email' => 'E-mail',
            'password' => 'Mot de passe',
            'new_password_hint' => 'Nouveau mot de passe (laisser vide pour conserver)',
            'super_admin' => 'Super-administrateur',
            'force_password_change' => 'Forcer le changement de mot de passe à la prochaine connexion',
        ],
        'sections' => [
            'account' => 'Compte',
            'security' => 'Actions de sécurité',
        ],
        'security' => [
            'reset_description' => 'Envoyer à cet utilisateur un e-mail avec un lien pour réinitialiser son mot de passe.',
            'send_reset' => 'Envoyer la réinitialisation du mot de passe',
        ],
        'memberships' => [
            'title' => 'Appartenances aux projets',
            'empty' => "N'appartient encore à aucun projet.",
            'add_label' => 'Ajouter à un projet',
            'select_project_placeholder' => 'Sélectionner un projet…',
            'remove_confirm' => [
                'title' => 'Retirer du projet ?',
                'text' => 'Retirer :name de :project ?',
                'confirm' => 'Retirer',
            ],
        ],
        'create' => [
            'title' => 'Ajouter un utilisateur',
        ],
        'edit' => [
            'title' => "Modifier l'utilisateur",
        ],
        'delete_confirm' => [
            'title' => "Supprimer l'utilisateur ?",
            'text' => 'Supprimer ":name" ? Cette action est irréversible.',
        ],
    ],
    'branding' => [
        'title' => 'Image de marque',
        'fields' => [
            'app_name' => "Nom de l'application",
            'app_name_hint' => 'Laisser vide pour utiliser la valeur par défaut ("Marketix").',
            'logo_light' => 'Logo (mode clair)',
            'logo_light_hint' => 'Affiché sur les fonds clairs.',
            'logo_dark' => 'Logo (mode sombre)',
            'logo_dark_hint' => 'Affiché sur les fonds sombres.',
            'logo_email' => 'Logo e-mail / PDF',
            'logo_email_hint' => 'Utilisé dans les e-mails et les rapports PDF.',
            'favicon' => 'Favicon',
            'favicon_hint' => '.ico, .png, .jpg.',
        ],
        'remove_current' => 'Retirer le fichier actuel',
    ],
    'mailer' => [
        'title' => 'Paramètres de messagerie',
        'fields' => [
            'default_mailer' => 'Messagerie active',
            'from_address' => 'Adresse expéditeur',
            'from_name' => "Nom de l'expéditeur",
            'postal_url' => 'URL du serveur Postal',
            'postal_key' => 'Clé API',
            'smtp_host' => 'Hôte',
            'smtp_port' => 'Port',
            'smtp_username' => "Nom d'utilisateur",
            'smtp_password' => 'Mot de passe',
            'smtp_scheme' => 'Chiffrement (ex. tls)',
        ],
        'options' => [
            'postal' => 'Postal',
            'smtp' => 'SMTP',
            'log' => 'Journal (aucun envoi)',
        ],
        'test' => [
            'title' => 'Envoyer un e-mail de test',
            'recipient_label' => 'Destinataire (par défaut votre adresse)',
            'send_button' => 'Envoyer le test',
        ],
    ],
    'storage' => [
        'title' => 'Paramètres de stockage',
        'driver_changed_warning' => 'Les fichiers existants (logos, favicons) restent sur le disque précédent et devront peut-être être ré-importés. Les nouveaux envois utiliseront le disque sélectionné.',
        'fields' => [
            'driver' => 'Backend de stockage',
            's3_key' => "Identifiant de clé d'accès",
            's3_secret' => "Clé d'accès secrète",
            's3_region' => 'Région',
            's3_bucket' => 'Bucket',
            's3_endpoint' => 'Point de terminaison',
            's3_endpoint_hint' => 'Laisser vide pour AWS ; renseigner pour Cloudflare R2, MinIO, DigitalOcean Spaces, Hetzner.',
            's3_use_path_style' => 'Utiliser un point de terminaison de style « chemin »',
        ],
        'options' => [
            'local' => 'Disque local',
            's3' => 'Compatible S3',
        ],
        'test_connection' => 'Tester la connexion',
    ],
];
