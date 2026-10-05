<?php

return [
    'privacy' => [
        'title' => 'Gegevensbescherming',
        'subtitle' => 'Hoe :app persoonsgegevens verwerkt en wat u nodig hebt voor AVG (DSGVO)-naleving.',
        'disclaimer' => 'Deze pagina biedt praktische hulp om te starten — het is geen juridisch advies. Raadpleeg voor een bindende beoordeling een gekwalificeerde privacydeskundige.',

        'collect' => [
            'heading' => 'Wat :app verzamelt',
            'intro' => 'Wanneer een bezoeker een van uw korte links of een gevolgde analysepagina opent, legt :app een kleine set technische gegevens vast om geaggregeerde statistieken te maken:',
            'ip' => 'IP-adres — wordt nooit onversleuteld opgeslagen. Het wordt samen met de browser en een willekeurige dagelijkse salt gehasht die na 48 uur wordt verwijderd, zodat de resulterende bezoekers-identificatie daarna niet meer aan een IP kan worden gekoppeld.',
            'geo' => 'Globale locatie (land, regio, stad) afgeleid uit het IP op het moment van de aanvraag.',
            'device' => 'Apparaattype, browser en besturingssysteem, afgeleid uit de User-Agent.',
            'referrer' => 'Referrer-domein — de website waar een bezoeker vandaan kwam.',
            'utm' => 'Campagneparameters (UTM-tags) die in de link aanwezig zijn.',
            'timestamp' => 'De datum en tijd van de klik of paginaweergave.',
            'engagement' => 'Interacties op gemeten pagina’s: actieve tijd op de pagina, scrolldiepte en klikken op uitgaande links en bestandsdownloads (alleen het doeladres, zonder queryparameters).',
            'search' => 'Zoektermen van de websitezoekfunctie – alleen als u dit per website uitdrukkelijk inschakelt.',
        ],

        'modes' => [
            'heading' => 'Trackingmodi & Do-Not-Track',
            'cookieless' => 'Cookieloze modus (standaard): bezoekers worden alleen herkend via de hierboven beschreven dagelijkse hash — er worden geen cookies opgeslagen en bezoekers kunnen niet van de ene dag op de andere worden gevolgd.',
            'cookie' => 'Cookiemodus (optioneel, per site): een first-party cookie (mx_vid) wordt opgeslagen zodat terugkerende bezoekers over meerdere bezoeken kunnen worden herkend. Deze wordt alleen gebruikt als u deze modus voor een site inschakelt, en pas nadat het toestemmingssignaal van de bezoeker is gegeven.',
            'dnt' => 'Do-Not-Track: elke site kan worden ingesteld om het „Do Not Track”-signaal van de browser te respecteren. Bezoekers die het verzenden, worden helemaal niet gevolgd.',
            'links_note' => 'Klikstatistieken van korte links zijn altijd cookieloos en worden aan de serverzijde gemeten — de cookiemodus geldt alleen voor de pagina-analysefunctie van :app.',
        ],

        'nocollect' => [
            'heading' => 'Wat :app niet doet',
            'cookies' => 'Geen cookies van derden en geen tracking over sites heen. Analyse is standaard cookieloos; een first-party cookie wordt alleen gebruikt als u een site op cookiemodus zet, en uitsluitend met toestemming (zie trackingmodi hierboven).',
            'raw_ip' => 'IP-adressen worden nooit onversleuteld in de database opgeslagen — er is geen IP-kolom.',
            'pii' => 'Namen, e-mailadressen of formulierinhoud worden niet verzameld door het klik- en analysetracking.',
            'cross' => 'Uw analysegegevens worden nooit verkocht of met derden gedeeld voor advertenties.',
        ],

        'retention' => [
            'heading' => 'Rechtsgrond & bewaartermijn',
            'basis' => 'De verwerking van klik- en analysegegevens is doorgaans gebaseerd op uw gerechtvaardigd belang bij bereikmeting (art. 6 lid 1 sub f AVG), of op toestemming van bezoekers waar uw rechtsgebied dit vereist.',
            'stats' => 'Ruwe klikstatistieken worden :stats maanden bewaard en daarna automatisch opgeschoond.',
            'analytics' => 'Ruwe paginaweergaven en sessies worden :analytics maanden bewaard (of korter als een site een kortere termijn instelt) en daarna opgeschoond.',
            'aggregates' => 'Geaggregeerde tellingen en grafieken kunnen langer worden bewaard; ze bevatten geen persoonlijke identificatoren.',
        ],

        'notice' => [
            'heading' => 'Privacyverklaring voor uw website',
            'intro' => 'Als u :app gebruikt om links of pagina’s te volgen, informeer dan uw bezoekers. U kunt de onderstaande tekst aanpassen voor uw eigen privacyverklaring:',
            'snippet' => 'Deze website gebruikt :app om het bereik van haar links en pagina’s te meten. Wanneer u een gevolgde link of pagina opent, worden technische gegevens (een gehasht IP-adres, de globale locatie, browser, besturingssysteem en de verwijzende website) verwerkt om anonieme statistieken te maken. Cookies worden alleen gebruikt als u instemt met cookiegebaseerde meting, en de gegevens worden niet gebruikt om u persoonlijk te identificeren.',
            'copy' => 'Verklaring kopiëren',
            'copied' => 'Gekopieerd',
        ],

        'responsibilities' => [
            'heading' => 'Uw verantwoordelijkheden als verwerkingsverantwoordelijke',
            'intro' => 'Wanneer u :app gebruikt, bent u de verwerkingsverantwoordelijke voor de gegevens van uw bezoekers. In het bijzonder dient u:',
            'policy' => 'Een privacyverklaring te publiceren die deze analyseverwerking vermeldt (u kunt de bovenstaande verklaring gebruiken).',
            'basis' => 'Ervoor te zorgen dat u een geldige rechtsgrond hebt — een afweging van gerechtvaardigd belang of toestemming — voor uw regio.',
            'consent' => 'Een trackingmodus en toestemmingsinstelling te kiezen die bij uw rechtsgrond passen — schakel de Do-Not-Track-optie en cookietoestemming in waar uw regio dit vereist.',
            'dpa' => 'Een verwerkersovereenkomst (DPA / AVV) te sluiten met de beheerder van uw :app-instantie, waar vereist.',
            'rights' => 'Verzoeken van betrokkenen af te handelen. Omdat bezoekers alleen via een dagelijks wisselende, niet-koppelbare hash worden geïdentificeerd, kunnen de meeste opgeslagen gegevens niet tot een persoon worden herleid.',
        ],
    ],
];
