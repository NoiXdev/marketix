<?php

return [
    'action' => 'Move to another project',
    'title' => 'Move “:name”',
    'project' => 'Target project',
    'project_placeholder' => 'Select a project…',
    'domain' => 'Target domain',
    'domain_placeholder' => 'Select a domain…',
    'domains_loading' => 'Loading domains…',
    'no_domains' => 'This project has no domains yet.',
    'open' => 'Move…',
    'submit' => 'Move',
    'moved' => 'Moved to “:project”.',
    'hints' => [
        'url' => 'Statistics, a linked QR code and its scheduled reports move along. Pixels stay here and are detached.',
        'qrcode' => 'Its short link, statistics and scheduled reports move along. Pixels stay here and are detached.',
        'domain' => 'All links on this domain move along, including their statistics, QR codes and scheduled reports. Pixels stay here and are detached.',
        'site' => 'All analytics data, goals, funnels and scheduled reports move along.',
        'pixel' => 'The pixel is detached from all links in this project.',
        'default' => 'It will then only be available in the target project.',
    ],
    'errors' => [
        'project' => 'Choose another project you have access to.',
        'domain' => 'Choose a domain of the target project.',
        'slug_taken' => 'The short link “:slug” is already in use on :domain.',
    ],
];
