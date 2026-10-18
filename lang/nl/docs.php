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
            'referrer' => 'De volledige verwijzende URL — het adres van de pagina waar een bezoeker vandaan kwam, niet alleen het domein.',
            'utm' => 'Campagneparameters (UTM-tags) die in de link aanwezig zijn.',
            'timestamp' => 'De datum en tijd van de klik of paginaweergave.',
            'title_hostname' => 'De paginatitel en hostname van de gevolgde pagina.',
            'engagement' => 'Interacties op gemeten pagina’s: actieve tijd op de pagina, scrolldiepte en klikken op uitgaande links en bestandsdownloads (alleen het doeladres, zonder queryparameters).',
            'search' => 'Zoektermen van de websitezoekfunctie – alleen als u dit per website uitdrukkelijk inschakelt.',
        ],

        'modes' => [
            'heading' => 'Trackingmodi & Do-Not-Track',
            'cookieless' => 'Cookieloze modus (standaard): bezoekers worden alleen herkend via de hierboven beschreven dagelijkse hash — er worden geen cookies opgeslagen en bezoekers kunnen niet van de ene dag op de andere worden gevolgd.',
            'hybrid' => 'Hybride modus (optioneel, per site): bezoekers worden eerst anoniem gemeten via de dagelijkse hash. Zodra een toestemmingssignaal is gegeven, neemt het first-party cookie (mx_vid) de herkenning over; daarbij wordt alleen de campagnebron van het voorgaande anonieme bezoek overgenomen.',
            'cookie' => 'Cookiemodus (optioneel, per site): een first-party cookie (mx_vid) wordt opgeslagen zodat terugkerende bezoekers over meerdere bezoeken kunnen worden herkend. Deze wordt alleen gebruikt als u deze modus voor een site inschakelt. Bij de toestemmingsinstelling „Direct” (standaard) wordt het geplaatst zonder voorafgaand toestemmingssignaal; alleen bij de instelling „Signaal van derde partij” wordt op dat signaal gewacht.',
            'dnt' => 'Do-Not-Track: elke site kan worden ingesteld om het „Do Not Track”-signaal van de browser te respecteren. Bezoekers die het verzenden, worden helemaal niet gevolgd.',
            'links_note' => 'Klikstatistieken van korte links zijn altijd cookieloos en worden aan de serverzijde gemeten — de cookie- en hybride modus gelden alleen voor de pagina-analysefunctie van :app.',
        ],

        'nocollect' => [
            'heading' => 'Wat :app niet doet',
            'cookies' => 'Projecten zonder gekoppelde retargeting-pixels plaatsen geen cookies van derden en voeren geen tracking over sites heen uit. Koppelt u echter een retargeting-pixel (bijv. Google Ads, Meta/Facebook, LinkedIn) aan een korte link, dan laadt de doorverwijspagina het script van die aanbieder voordat deze doorverwijst; die aanbieder plaatst vervolgens zijn eigen cookies van derden en ontvangt gegevens — ongeacht toestemming. De first-party analyse (dagelijkse hash of mx_vid-cookie) wordt hierdoor niet beïnvloed; zie trackingmodi hierboven voor de details daarvan.',
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

        'generator' => [
            'heading' => 'Privacyverklaring-generator',
            'intro' => 'Stelt een tekst samen op basis van wat dit project daadwerkelijk verwerkt. Er wordt niets opgeslagen — de tekst wordt bij elke wijziging opnieuw samengesteld.',

            'role' => [
                'label' => 'Wie is de verwerkingsverantwoordelijke?',
                'own_company' => [
                    'title' => 'Ons eigen bedrijf',
                    'text' => 'U meet voor uw eigen bedrijf. Er is geen aparte opdrachtgever.',
                ],
                'client' => [
                    'title' => 'Klantproject',
                    'text' => 'U meet in opdracht van een klant. De klant is verwerkingsverantwoordelijke, uw bureau is verwerker.',
                ],
            ],

            'controller' => [
                'heading' => 'Verwerkingsverantwoordelijke',
                'description' => 'Wordt met naam in de tekst genoemd.',
                'name' => 'Naam',
                'address' => 'Adres',
                'name_placeholder' => 'Voorbeeld B.V.',
                'address_placeholder' => 'Voorbeeldstraat 1, 1015 Amsterdam',
                'locked' => 'Deze gegevens komen uit de project- en beheerdersinstellingen en kunnen alleen door een instantiebeheerder worden gewijzigd.',
            ],

            'processors' => [
                'heading' => 'Verwerkers',
                'description' => 'Overgenomen uit de beheerdersinstellingen — hier alleen ter inzage, zodat u ziet wat er in de tekst komt te staan.',
                'agency' => 'Bureau',
                'hosting' => 'Hostingprovider',
                'location' => 'Serverlocatie',
                'empty' => 'Niet ingevuld',
            ],

            'activities' => [
                'heading' => 'Wat actief is',
                'description' => 'Vooraf ingevuld op basis van de projectconfiguratie. Zet uit wat de tekst niet mag noemen.',
                'inactive' => 'Niet aangetroffen in dit project.',
                'link_clicks' => [
                    'title' => 'Klikstatistieken van verkorte links',
                    'text' => 'Bij elke klik aan de serverzijde vastgelegd. Verkorte links in dit project: :count',
                ],
                'site_analytics' => [
                    'title' => 'Website-analyse',
                    'text' => 'Paginaweergaven en bezoeken op de gevolgde websites.',
                ],
                'pixels' => [
                    'title' => 'Retargeting-pixels',
                    'text' => 'De doorstuurpagina laadt het script van de aanbieder voordat er wordt doorgestuurd. Het product kent hiervoor geen toestemmingsvraag.',
                    'flag' => 'Zonder toestemming',
                    'providers' => 'Aanbieders: :providers',
                    'links' => 'Betrokken verkorte links: :count',
                ],
                'outbound' => [
                    'title' => 'Uitgaande links en downloads',
                    'text' => 'Klikken op externe links en bestandsdownloads op gevolgde websites.',
                ],
                'site_search' => [
                    'title' => 'Zoeken op de website',
                    'text' => 'Zoektermen die in de zoekfunctie van een gevolgde website worden ingevoerd.',
                ],
                'custom_events' => [
                    'title' => 'Eigen events',
                    'text' => 'Events die uw eigen code naar de tracker stuurt.',
                ],
            ],

            'legal_basis' => [
                'label' => 'Rechtsgrondslag',
                'legitimate_interest' => [
                    'title' => 'Gerechtvaardigd belang',
                    'text' => 'Art. 6 lid 1 sub f AVG — bereikmeting zonder toestemming.',
                ],
                'consent' => [
                    'title' => 'Toestemming',
                    'text' => 'Art. 6 lid 1 sub a AVG — de meting start pas na toestemming.',
                ],
            ],

            'language' => [
                'label' => 'Taal van de tekst',
                'hint' => 'Verandert alleen de taal van de gegenereerde tekst, niet die van de interface.',
            ],

            'warnings' => [
                'heading' => 'Controleer vóór publicatie',
            ],

            'preview' => [
                'heading' => 'Voorbeeld',
                'loading' => 'Bezig met genereren…',
                'error' => 'De tekst kon niet worden gegenereerd. Probeer het opnieuw.',
                'copy_text' => 'Kopiëren als tekst',
                'copy_html' => 'Kopiëren als HTML',
                'copied' => 'Gekopieerd',
                'placeholders' => 'Nog te vervangen: :list',
            ],
        ],
    ],
];
