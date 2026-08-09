<?php

return [
    'title' => 'Team',
    'subtitle' => 'Mitglieder und Einladungen für dieses Projekt verwalten',
    'invite' => [
        'label' => 'Per E-Mail einladen',
        'email_placeholder' => 'person@beispiel.de',
        'submit' => 'Einladen',
    ],
    'members' => [
        'heading' => 'Mitglieder',
        'columns' => [
            'name' => 'Name',
            'email' => 'E-Mail',
            'role' => 'Rolle',
        ],
    ],
    'invitations' => [
        'heading' => 'Ausstehende Einladungen',
        'expired_badge' => 'Abgelaufen',
        'expires_at' => 'Läuft ab am :date',
        'resend_title' => 'Einladung erneut senden',
        'resend_disabled_title' => 'Kürzlich gesendet — bitte in Kürze erneut versuchen',
    ],
    'confirm' => [
        'revoke_title' => 'Einladung widerrufen?',
        'revoke_text' => 'Die Einladung für :email widerrufen?',
        'revoke_button' => 'Widerrufen',
        'remove_title' => 'Mitglied entfernen?',
        'remove_text' => ':name entfernen?',
        'remove_button' => 'Entfernen',
    ],
];
