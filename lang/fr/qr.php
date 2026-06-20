<?php

return [
    'edit' => [
        'confirm' => [
            'title'  => 'Enregistrer cette modification ?',
            'text'   => "Cette modification change l'image du QR code. Les codes déjà imprimés ou partagés ne correspondront plus et devront être régénérés.",
            'button' => 'Enregistrer quand même',
        ],
    ],

    'versions' => [
        'title'   => 'Historique des versions',
        'empty'   => 'Aucune version pour le moment.',
        'current' => 'Actuelle',
        'restore' => 'Restaurer',
        'by'      => 'par :name',
        'dynamic' => 'Dynamique',
        'static'  => 'Statique',
        'restore_confirm' => [
            'title'  => 'Restaurer cette version ?',
            'text'   => "Cela remplace le QR actuel par la version sélectionnée et change l'image du QR. Les codes déjà imprimés doivent être régénérés. Votre version actuelle est conservée dans l'historique.",
            'button' => 'Restaurer',
        ],
    ],
];
