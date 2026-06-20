<?php

return [
    'title' => 'Profil',
    'name' => 'Nom',
    'email' => 'E-mail',
    'password' => [
        'heading' => 'Modifier le mot de passe',
        'current' => 'Mot de passe actuel',
        'new' => 'Nouveau mot de passe',
        'confirm' => 'Confirmer le nouveau mot de passe',
        'submit' => 'Enregistrer',
    ],
    'two_factor' => [
        'heading' => 'Authentification à deux facteurs',
        'recovery_codes_notice' => 'Conservez ces codes de récupération dans un endroit sûr. Ils ne sont affichés qu\'une seule fois.',
        'enable' => 'Activer l\'authentification à deux facteurs',
        'scan_instruction' => 'Scannez ce QR code avec votre application d\'authentification, puis saisissez le code généré pour terminer.',
        'qr_alt' => 'QR code d\'authentification à deux facteurs',
        'manual_key' => 'Ou saisissez cette clé manuellement :',
        'confirm' => 'Confirmer',
        'enabled' => 'L\'authentification à deux facteurs est activée.',
        'current_password' => 'Mot de passe actuel',
        'disable' => 'Désactiver',
        'regenerate' => 'Régénérer les codes de récupération',
    ],
    'passkeys' => [
        'heading' => 'Passkeys',
        'not_supported' => 'Ce navigateur ne prend pas en charge les passkeys.',
        'security_key' => 'Clé de sécurité',
        'last_used' => 'dernière utilisation',
        'remove' => 'Supprimer',
        'name_label' => 'Nom du passkey',
        'add' => 'Ajouter un passkey',
    ],
];
