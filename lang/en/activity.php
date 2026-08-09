<?php

return [
    'title' => 'Activity',
    'all' => 'All activity',
    'feed' => [
        'labels' => [
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'login' => 'signed in',
            'password_changed' => 'changed their password',
            'password_reset' => 'reset their password',
            'two_factor_enabled' => 'enabled two-factor auth',
            'two_factor_disabled' => 'disabled two-factor auth',
            'passkey_added' => 'added a passkey',
            'passkey_removed' => 'removed a passkey',
            'passkey_renamed' => 'renamed a passkey',
            'member_removed' => 'removed a member',
            'role_changed' => 'changed a member role',
            'invitation_sent' => 'sent an invitation',
            'invitation_revoked' => 'revoked an invitation',
            'invitation_resent' => 'resent an invitation',
            'invitation_accepted' => 'accepted an invitation',
        ],
        'subject_line' => ':verb a :subject',
    ],
    'history' => [
        'title' => 'History',
        'loading' => 'Loading…',
        'empty' => 'No history yet.',
    ],
];
