<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Inertia v3 changed the default page path from `resources/js/Pages` to the
    | lowercase `resources/js/pages` used by the newer Laravel starter kits.
    | This project uses the capitalised directory, so the path is overridden
    | here. Without it `assertInertia()->component()` fails with "Inertia page
    | component file [...] does not exist." on case-sensitive filesystems (CI),
    | while still passing on a case-insensitive one (macOS/DDEV).
    |
    | `mergeConfigFrom()` merges only top-level keys, so the whole `pages` block
    | has to be restated here — the remaining Inertia config keys still come
    | from the package defaults.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
