<?php

return [
    'edit' => [
        'confirm' => [
            'title'  => 'Save this change?',
            'text'   => 'This edit changes the QR code image. Codes you have already printed or shared will stop matching and must be regenerated.',
            'button' => 'Save anyway',
        ],
    ],

    'versions' => [
        'title'   => 'Version history',
        'empty'   => 'No versions yet.',
        'current' => 'Current',
        'restore' => 'Restore',
        'by'      => 'by :name',
        'dynamic' => 'Dynamic',
        'static'  => 'Static',
        'restore_confirm' => [
            'title'  => 'Restore this version?',
            'text'   => 'This replaces the current QR with the selected version and changes the QR image. Already-printed codes must be regenerated. Your current version is kept in history.',
            'button' => 'Restore',
        ],
    ],
];
