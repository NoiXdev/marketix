<?php

return [
    'action' => 'Naar ander project verplaatsen',
    'title' => '“:name” verplaatsen',
    'project' => 'Doelproject',
    'project_placeholder' => 'Kies een project…',
    'domain' => 'Doeldomein',
    'domain_placeholder' => 'Kies een domein…',
    'domains_loading' => 'Domeinen laden…',
    'no_domains' => 'Dit project heeft nog geen domeinen.',
    'open' => 'Verplaatsen…',
    'submit' => 'Verplaatsen',
    'moved' => 'Verplaatst naar “:project”.',
    'hints' => [
        'url' => 'Statistieken, een gekoppelde QR-code en geplande rapporten gaan mee. Pixels blijven hier en worden ontkoppeld.',
        'qrcode' => 'De korte link, statistieken en geplande rapporten gaan mee. Pixels blijven hier en worden ontkoppeld.',
        'domain' => 'Alle links op dit domein gaan mee, inclusief statistieken, QR-codes en geplande rapporten. Pixels blijven hier en worden ontkoppeld.',
        'site' => 'Alle analyticsgegevens, doelen, funnels en geplande rapporten gaan mee.',
        'pixel' => 'De pixel wordt ontkoppeld van alle links in dit project.',
        'default' => 'Daarna is het alleen nog in het doelproject beschikbaar.',
    ],
    'errors' => [
        'project' => 'Kies een ander project waartoe u toegang hebt.',
        'domain' => 'Kies een domein van het doelproject.',
        'slug_taken' => 'De korte link “:slug” is al in gebruik op :domain.',
    ],
];
