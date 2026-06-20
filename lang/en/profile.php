<?php

return [
    'title' => 'Profile',
    'name' => 'Name',
    'email' => 'Email',
    'password' => [
        'heading' => 'Change password',
        'current' => 'Current password',
        'new' => 'New password',
        'confirm' => 'Confirm new password',
        'submit' => 'Save',
    ],
    'two_factor' => [
        'heading' => 'Two-factor authentication',
        'recovery_codes_notice' => 'Store these recovery codes somewhere safe. They are shown only once.',
        'enable' => 'Enable two-factor authentication',
        'scan_instruction' => 'Scan this QR code with your authenticator app, then enter the generated code to finish.',
        'qr_alt' => 'Two-factor QR code',
        'manual_key' => 'Or enter this key manually:',
        'confirm' => 'Confirm',
        'enabled' => 'Two-factor authentication is enabled.',
        'current_password' => 'Current password',
        'disable' => 'Disable',
        'regenerate' => 'Regenerate recovery codes',
    ],
    'passkeys' => [
        'heading' => 'Passkeys',
        'not_supported' => 'This browser does not support passkeys.',
        'security_key' => 'Security key',
        'last_used' => 'last used',
        'remove' => 'Remove',
        'name_label' => 'Passkey name',
        'add' => 'Add passkey',
    ],
];
