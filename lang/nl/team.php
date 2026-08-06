<?php

return [
    'title' => 'Team',
    'subtitle' => 'Beheer leden en uitnodigingen voor dit project',
    'invite' => [
        'label' => 'Uitnodigen per e-mail',
        'email_placeholder' => 'persoon@voorbeeld.nl',
        'submit' => 'Uitnodigen',
    ],
    'members' => [
        'heading' => 'Leden',
        'columns' => [
            'name' => 'Naam',
            'email' => 'E-mail',
            'role' => 'Rol',
        ],
    ],
    'invitations' => [
        'heading' => 'Openstaande uitnodigingen',
        'expired_badge' => 'Verlopen',
        'expires_at' => 'Verloopt op :date',
        'resend_title' => 'Uitnodiging opnieuw versturen',
        'resend_disabled_title' => 'Recent verstuurd — probeer het straks opnieuw',
    ],
    'confirm' => [
        'revoke_title' => 'Uitnodiging intrekken?',
        'revoke_text' => 'De uitnodiging voor :email intrekken?',
        'revoke_button' => 'Intrekken',
        'remove_title' => 'Lid verwijderen?',
        'remove_text' => ':name verwijderen?',
        'remove_button' => 'Verwijderen',
    ],
];
