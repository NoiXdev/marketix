<?php

return [
    'title' => 'Domains',
    'subtitle' => 'Manage custom domains for this project',
    'create' => 'Add domain',
    'empty' => 'No domains yet',
    'empty_hint' => 'Add a custom domain to start using it for your links.',
    'columns' => [
        'domain' => 'Domain',
        'root_redirect' => 'Root redirect',
        'not_found_redirect' => '404 redirect',
        'status' => 'Status',
    ],
    'status' => [
        'dns' => 'DNS',
        'reachable' => 'Reachable',
        'ssl' => 'SSL',
    ],
    'never_checked' => 'Never checked',
    'actions' => [
        'check' => 'Check status',
    ],
    'delete' => [
        'title' => 'Delete domain?',
        'confirm' => 'Delete ":name"? This cannot be undone.',
    ],
];
