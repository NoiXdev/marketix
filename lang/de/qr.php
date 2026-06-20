<?php

return [
    'edit' => [
        'confirm' => [
            'title'  => 'Änderung speichern?',
            'text'   => 'Diese Änderung verändert das QR-Code-Bild. Bereits gedruckte oder geteilte Codes passen dann nicht mehr und müssen neu erstellt werden.',
            'button' => 'Trotzdem speichern',
        ],
    ],

    'versions' => [
        'title'   => 'Versionsverlauf',
        'empty'   => 'Noch keine Versionen.',
        'current' => 'Aktuell',
        'restore' => 'Wiederherstellen',
        'by'      => 'von :name',
        'dynamic' => 'Dynamisch',
        'static'  => 'Statisch',
        'restore_confirm' => [
            'title'  => 'Diese Version wiederherstellen?',
            'text'   => 'Dadurch wird der aktuelle QR-Code durch die gewählte Version ersetzt und das QR-Bild ändert sich. Bereits gedruckte Codes müssen neu erstellt werden. Deine aktuelle Version bleibt im Verlauf erhalten.',
            'button' => 'Wiederherstellen',
        ],
    ],
];
