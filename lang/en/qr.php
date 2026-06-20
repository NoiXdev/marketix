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

    'vcard' => [
        'drop'   => 'Drop a .vcf file here, or click to upload',
        'error'  => "Couldn't read a contact from that file",
        'pick'   => 'This file has :count contacts — pick one',
        'extras' => 'Extra fields kept from file (:count): :fields',
        'clear'  => 'Clear extras',
    ],
];
