<?php

return [
    'title' => 'Équipe',
    'subtitle' => 'Gérer les membres et les invitations de ce projet',
    'invite' => [
        'label' => 'Inviter par e-mail',
        'email_placeholder' => 'personne@exemple.com',
        'submit' => 'Inviter',
    ],
    'members' => [
        'heading' => 'Membres',
        'columns' => [
            'name' => 'Nom',
            'email' => 'E-mail',
            'role' => 'Rôle',
        ],
    ],
    'invitations' => [
        'heading' => 'Invitations en attente',
        'expired_badge' => 'Expirée',
        'expires_at' => 'Expire le :date',
        'resend_title' => "Renvoyer l'invitation",
        'resend_disabled_title' => 'Envoyée récemment — réessayez bientôt',
    ],
    'confirm' => [
        'revoke_title' => "Révoquer l'invitation ?",
        'revoke_text' => "Révoquer l'invitation pour :email ?",
        'revoke_button' => 'Révoquer',
        'remove_title' => 'Retirer ce membre ?',
        'remove_text' => 'Retirer :name ?',
        'remove_button' => 'Retirer',
    ],
];
