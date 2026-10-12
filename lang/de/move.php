<?php

return [
    'action' => 'In anderes Projekt verschieben',
    'title' => '„:name“ verschieben',
    'project' => 'Zielprojekt',
    'project_placeholder' => 'Projekt auswählen…',
    'domain' => 'Ziel-Domain',
    'domain_placeholder' => 'Domain auswählen…',
    'domains_loading' => 'Domains werden geladen…',
    'no_domains' => 'Dieses Projekt hat noch keine Domains.',
    'open' => 'Verschieben…',
    'submit' => 'Verschieben',
    'moved' => 'Nach „:project“ verschoben.',
    'hints' => [
        'url' => 'Statistiken, ein verknüpfter QR-Code und geplante Berichte werden mitverschoben. Pixel bleiben hier und werden entfernt.',
        'qrcode' => 'Der Kurzlink, seine Statistiken und geplante Berichte werden mitverschoben. Pixel bleiben hier und werden entfernt.',
        'domain' => 'Alle Links dieser Domain werden mitverschoben, inklusive Statistiken, QR-Codes und geplanter Berichte. Pixel bleiben hier und werden entfernt.',
        'site' => 'Alle Analytics-Daten, Ziele, Funnels und geplanten Berichte werden mitverschoben.',
        'pixel' => 'Der Pixel wird von allen Links dieses Projekts entfernt.',
        'default' => 'Danach ist es nur noch im Zielprojekt verfügbar.',
    ],
    'errors' => [
        'project' => 'Wählen Sie ein anderes Projekt, auf das Sie Zugriff haben.',
        'domain' => 'Wählen Sie eine Domain des Zielprojekts.',
        'slug_taken' => 'Der Kurzlink „:slug“ ist auf :domain bereits vergeben.',
    ],
];
