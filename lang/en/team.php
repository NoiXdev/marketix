<?php

return [
    'title' => 'Team',
    'subtitle' => 'Manage members and invitations for this project',
    'invite' => [
        'label' => 'Invite by email',
        'email_placeholder' => 'person@example.com',
        'submit' => 'Invite',
    ],
    'members' => [
        'heading' => 'Members',
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
        ],
    ],
    'invitations' => [
        'heading' => 'Pending invitations',
        'expired_badge' => 'Expired',
        'expires_at' => 'Expires :date',
        'resend_title' => 'Resend invitation',
        'resend_disabled_title' => 'Recently sent — try again shortly',
    ],
    'confirm' => [
        'revoke_title' => 'Revoke invitation?',
        'revoke_text' => 'Revoke the invitation for :email?',
        'revoke_button' => 'Revoke',
        'remove_title' => 'Remove member?',
        'remove_text' => 'Remove :name?',
        'remove_button' => 'Remove',
    ],
];
