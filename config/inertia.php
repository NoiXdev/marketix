<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | Marketix renders on the client only and ships no SSR bundle. Inertia
    | enables SSR by default, which made every page render in development
    | POST to the Vite dev server's __inertia_ssr endpoint (answered with a
    | 404). Off unless INERTIA_SSR_ENABLED says otherwise; the rest of the
    | block restates the package defaults because only top-level keys merge.
    |
    */

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),

        'runtime' => env('INERTIA_SSR_RUNTIME', 'node'),

        'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS', false),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        'hot_url' => env('INERTIA_SSR_HOT_URL'),

        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),

        'throw_on_error' => (bool) env('INERTIA_SSR_THROW_ON_ERROR', false),

    ],

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
