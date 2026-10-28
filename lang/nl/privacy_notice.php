<?php

/*
|--------------------------------------------------------------------------
| Privacy notice generator — Dutch
|--------------------------------------------------------------------------
|
| Translated from `lang/de/privacy_notice.php`, which is the reference
| catalog. The same rules apply here:
|
|   - The `pixels` block cites no legal basis and makes no consent claim.
|   - Nothing here may promise a right that cannot be exercised.
|
| Two things are deliberately not carried over literally from the German:
|
|   - § 25 (1) TDDDG is German law and has no meaning elsewhere, so
|     `legal_basis.consent` cites Article 5(3) of the ePrivacy Directive
|     and its national implementation instead. The GDPR articles are
|     EU-wide and are cited unchanged.
|   - Section names quoted inside a sentence must match the headings in
|     this file, not the German ones.
|
| The `legal_basis.activities_*` and `rights.anonymous_scope_*` entries are
| sentence fragments, not standalone sentences: they are read into a
| carrier sentence beginning with "voor" or "bij".
|
| The key set and the `:token` placeholders per key are identical to the
| German file and are checked by NoticeTranslationsTest.
|
*/

return [

    // Visible stand-ins for party details that were left empty. They must
    // read as gaps in the text, never as prose.
    'placeholders' => [
        'controller_name' => '[Naam van de verwerkingsverantwoordelijke]',
        'controller_address' => '[Adres van de verwerkingsverantwoordelijke]',
        'agency_name' => '[Naam van de verwerker]',
        'agency_address' => '[Adres van de verwerker]',
        'hosting_provider' => '[Naam van de hostingaanbieder]',
        'hosting_location' => '[Locatie van de servers]',
    ],

    'roles' => [
        'own_company' => 'Eigen onderneming',
        'client' => 'Klantproject',
    ],

    'legal_bases' => [
        'legitimate_interest' => 'Gerechtvaardigd belang (artikel 6, lid 1, onder f), AVG)',
        'consent' => 'Toestemming (artikel 6, lid 1, onder a), AVG)',
    ],

    // Rendered in months when the configured retention divides evenly by
    // 30, otherwise in days.
    'duration' => [
        'months' => ':count maand|:count maanden',
        'days' => ':count dag|:count dagen',
    ],

    'controller' => [
        'heading' => 'Verwerkingsverantwoordelijke',
        'intro' => 'Verantwoordelijk voor de hierna beschreven verwerking in de zin van artikel 4, punt 7), AVG is:',
        'party' => ':name, :address',
    ],

    'processors' => [
        'heading' => 'Verwerking in opdracht en hosting',
        'agency' => 'De meting wordt in onze opdracht en uitsluitend volgens onze instructies uitgevoerd door :name, :address, als verwerker in de zin van artikel 28 AVG.',
        'own_company' => 'De software die voor de meting wordt gebruikt, beheren wij zelf. De meetgegevens worden niet door een externe dienstverlener geanalyseerd.',
        'hosting' => 'De servers waarop de meetgegevens worden opgeslagen, worden beheerd door :provider. Locatie van de servers: :location.',
        'transfer_pixels' => 'Daarnaast worden bij het openen van korte links gegevens doorgegeven aan de onder “Retargetingpixels” genoemde aanbieders, overwegend naar de Verenigde Staten van Amerika.',
        'no_advertising_transfer' => 'De meetgegevens worden niet aan derden doorgegeven voor reclame- of marketingdoeleinden.',
    ],

    'clicks' => [
        'heading' => 'Registratie van klikken op korte links',
        'intro' => 'Voor een deel van de links die wij verspreiden, worden korte links gebruikt die bij het openen doorverwijzen naar het eigenlijke doeladres. Elke aanvraag van zo’n korte link wordt aan de serverzijde geteld; daarvoor wordt geen eigen cookie geplaatst.',
        'data' => 'Daarbij worden opgeslagen: de onder “Omgang met het IP-adres” beschreven hashwaarde, het land, de landcode en de stad, de taal die uit de eerste twee tekens van de taalinstelling van uw browser wordt afgeleid, het volledige verwijzende adres (referrer), de gebruikte browser, het besturingssysteem, een aanduiding of de aanvraag van een geautomatiseerd programma afkomstig is, en het tijdstip van de aanvraag.',
    ],

    'analytics' => [
        'heading' => 'Statistische analyse van het websitegebruik',
        'intro' => 'Het gebruik van de volgende websites wordt statistisch geanalyseerd:',

        'site' => [
            'cookieless' => ':domain — De meting vindt plaats zonder cookies. Aanvragen worden uitsluitend aan elkaar gekoppeld via de onder “Omgang met het IP-adres” beschreven hashwaarde.',
            'hybrid' => ':domain — De meting vindt eerst zonder cookies plaats. Pas wanneer toestemming is gegeven, wordt daarnaast het cookie “mx_vid” geplaatst en wordt het bezoek daarmee herkend.',
            'cookie_immediately' => ':domain — Het cookie “mx_vid” wordt al bij de eerste aanvraag geplaatst; verdere bezoeken worden daarmee herkend.',
            'cookie_after_consent' => ':domain — Het cookie “mx_vid” wordt geplaatst zodra het toestemmingsinstrument van deze website toestemming signaleert; verdere bezoeken worden daarmee herkend.',
            'dnt' => 'Als uw browser de instelling “Do Not Track” meestuurt, vindt er voor deze website geen meting plaats.',
        ],

        'page_view' => 'Bij elke paginaweergave worden opgeslagen: het opgevraagde pad zonder queryparameters, de paginatitel, de hostnaam, het volledige verwijzende adres (referrer) en het domein daarvan, het land, de landcode, de stad en de regio, de browser, het besturingssysteem en het apparaattype, de taalinstelling, de in het opgevraagde adres opgenomen campagneparameters (utm_source, utm_medium, utm_campaign en andere), een aanduiding of de aanvraag van een geautomatiseerd programma afkomstig is, de actieve verblijfsduur in seconden, de bereikte scrolldiepte en het tijdstip van de aanvraag.',
        'visit' => 'Per samenhangend bezoek worden daarnaast de instappagina, de uitstappagina, de aanduiding of het om een terugkerend bezoek gaat en de campagnebron van het eerste contact opgeslagen.',
        'outbound' => 'Op de volgende websites worden klikken op links die naar andere websites leiden, samen met het betreffende doeladres als gebeurtenis opgeslagen: :domains.',
        'downloads' => 'Op de volgende websites worden klikken op downloadbare bestanden samen met het adres van het bestand als gebeurtenis opgeslagen: :domains.',
        'contact_links' => 'Op de volgende websites worden klikken op telefoon- en e-maillinks, samen met het aangeklikte telefoonnummer of e-mailadres, als gebeurtenis opgeslagen: :domains.',
        'site_search' => 'Op de volgende websites worden termen die via de zoekfunctie worden ingevoerd, als gebeurtenis opgeslagen: :domains. Daarbij worden ze omgezet naar kleine letters en na 100 tekens afgekapt.',
        'custom_events' => 'Daarnaast kunnen verdere gebeurtenissen worden vastgelegd. Zo’n gebeurtenis bestaat uit een naam en kan aanvullende gegevens van maximaal 1 kilobyte bevatten; welke gegevens dat zijn, bepaalt degene die de meetcode in de website heeft opgenomen.',
    ],

    'ip' => [
        'heading' => 'Omgang met het IP-adres',
        'hash' => 'Uw IP-adres wordt niet opgeslagen. Het wordt uitsluitend vluchtig verwerkt om daaruit, samen met de identificatie van uw browser (user agent), een bereikaanduiding en een willekeurige dagsleutel, een onomkeerbare hashwaarde (SHA-256) te berekenen. Alleen deze hashwaarde wordt opgeslagen.',
        'salt' => 'De dagsleutel wordt dagelijks opnieuw gegenereerd en uiterlijk na 48 uur verwijderd. Daarna kan de opgeslagen hashwaarde niet meer aan een IP-adres worden gekoppeld; de betreffende gegevens zijn vanaf dat moment anoniem.',
        'scope_both' => 'De bereikaanduiding is bij korte links het betreffende project en bij de websitemeting de betreffende website. Dezelfde persoon krijgt daardoor in verschillende projecten en op verschillende websites verschillende hashwaarden en is daarmee niet overkoepelend herkenbaar.',
        'scope_clicks' => 'De bereikaanduiding is het betreffende project. Dezelfde persoon krijgt daardoor in verschillende projecten verschillende hashwaarden en is daarmee niet projectoverstijgend herkenbaar.',
        'scope_sites' => 'De bereikaanduiding is de betreffende website. Dezelfde persoon krijgt daardoor op verschillende websites verschillende hashwaarden en is daarmee niet websiteoverstijgend herkenbaar.',
        'geolocation' => 'Het toewijzen van land, regio en stad gebeurt via een lokaal op de server geïnstalleerde database (MaxMind GeoLite2-City). Uw IP-adres wordt hiervoor aan niemand doorgegeven en verlaat de server niet.',
        'data_center' => 'Voor de statistische analyse van de websites wordt via een lokaal op de server geïnstalleerde database (MaxMind GeoLite2-ASN) bovendien vastgesteld tot welke netwerkbeheerder uw IP-adres behoort. Komt de aanvraag uit het netwerk van een datacenter of hostingprovider, dan wordt zij aangeduid als afkomstig van een geautomatiseerd programma. De netwerkbeheerder zelf wordt niet opgeslagen; ook hiervoor wordt uw IP-adres aan niemand doorgegeven en verlaat het de server niet.',
    ],

    'cookies' => [
        'heading' => 'Cookies',
        'properties' => 'Voor de websitemeting wordt een cookie met de naam “mx_vid” gebruikt. Het wordt door de betreffende website zelf geplaatst (first-partycookie), heeft een looptijd van 365 dagen, draagt de instelling SameSite=Lax en wordt bij een aanvraag via HTTPS bovendien als “Secure” gemarkeerd.',
        'purpose' => 'Het cookie bevat uitsluitend een willekeurig gegenereerde identificatie. Die dient er alleen toe om terugkerende bezoeken van dezelfde persoon aan dezelfde website te herkennen; een analyse die verder reikt dan deze website vindt daarmee niet plaats.',
        'set_immediately' => 'Op de volgende websites wordt het cookie al bij de eerste aanvraag geplaatst: :domains.',
        'set_after_consent' => 'Op de volgende websites wordt het cookie pas geplaatst nadat toestemming is gegeven: :domains.',
        'control' => 'U kunt het cookie op elk moment via de instellingen van uw browser verwijderen en het plaatsen van cookies daar ook op voorhand blokkeren.',
    ],

    // Cites no legal basis and makes no consent claim — on purpose.
    'pixels' => [
        'heading' => 'Retargetingpixels',
        'intro' => 'Aan een deel van de gebruikte korte links zijn marketingdiensten gekoppeld. Wordt zo’n korte link geopend, dan volgt de doorverwijzing niet onmiddellijk: eerst wordt een tussenpagina geleverd die de scripts van de hieronder genoemde aanbieders laadt en u vervolgens naar het doeladres doorstuurt.',
        'providers' => 'Daarbij worden de diensten van de volgende aanbieders opgenomen: :providers.',
        'transmission' => 'Deze scripts worden door de genoemde aanbieders geleverd en draaien in uw browser. Zij plaatsen eigen cookies en geven gegevens door aan de betreffende aanbieder, waaronder uw IP-adres, de identificatie van uw browser en het opgevraagde adres. De doorgifte vindt overwegend plaats naar de Verenigde Staten van Amerika.',
        'vendor_notices' => 'Over de verwerking door de genoemde aanbieders en over uw rechten tegenover hen informeren hun eigen privacyverklaringen.',
    ],

    // Governs measurement only: link clicks and site analytics. The pixel
    // section is deliberately outside its scope, which is why the covered
    // activities are enumerated rather than implied.
    'legal_basis' => [
        'heading' => 'Rechtsgrondslag',
        'activities_both' => 'het registreren van klikken op korte links en de statistische analyse van het websitegebruik',
        'activities_clicks' => 'het registreren van klikken op korte links',
        'activities_sites' => 'de statistische analyse van het websitegebruik',
        'legitimate_interest' => 'De rechtsgrondslag voor :activities is artikel 6, lid 1, onder f), AVG.',
        'legitimate_interest_detail' => 'Ons gerechtvaardigd belang bestaat erin inzicht te krijgen in het bereik en het gebruik van ons aanbod en dit op basis daarvan te kunnen verbeteren.',
        'clicks_legitimate_interest' => 'De rechtsgrondslag voor het registreren van klikken op korte links is artikel 6, lid 1, onder f), AVG. De telling vindt aan de serverzijde plaats telkens wanneer een korte link wordt geopend; ons gerechtvaardigd belang bestaat erin inzicht te krijgen in het bereik van de door ons verspreide links.',
        'consent' => 'De rechtsgrondslag voor :activities is uw toestemming op grond van artikel 6, lid 1, onder a), AVG. Voor zover daarbij informatie op uw eindapparaat wordt opgeslagen of daaruit wordt uitgelezen, berust dit bovendien op artikel 5, lid 3, van de e-privacyrichtlijn (2002/58/EG) en op de nationale omzetting daarvan.',
        'consent_withdrawal' => 'U kunt een gegeven toestemming op elk moment intrekken met werking voor de toekomst. De rechtmatigheid van de verwerking die tot de intrekking heeft plaatsgevonden, blijft daardoor onaangetast.',
    ],

    'retention' => [
        'heading' => 'Bewaartermijn',
        'clicks' => 'De gegevens die over klikken op korte links zijn opgeslagen, worden na afloop van de bewaartermijn automatisch en definitief verwijderd. De bewaartermijn bedraagt :duration.',
        'sites_uniform' => 'De in het kader van de websitemeting opgeslagen gegevens worden na afloop van de bewaartermijn automatisch en definitief verwijderd. De bewaartermijn bedraagt :duration.',
        'sites_intro' => 'De in het kader van de websitemeting opgeslagen gegevens worden na afloop van de bewaartermijn automatisch en definitief verwijderd. De bewaartermijn is per website vastgelegd:',
        'site' => ':domain — :duration.',
    ],

    'rights' => [
        'heading' => 'Uw rechten',
        'list_legitimate_interest' => 'U hebt het recht om inzage te vragen in de over u opgeslagen persoonsgegevens (artikel 15 AVG) en om rectificatie daarvan (artikel 16 AVG), wissing daarvan (artikel 17 AVG) of beperking van de verwerking (artikel 18 AVG) te verlangen. Daarnaast kunt u bezwaar maken tegen de verwerking om redenen die verband houden met uw specifieke situatie (artikel 21 AVG). Neem hiervoor contact op met de hierboven genoemde verwerkingsverantwoordelijke.',
        'list_consent' => 'U hebt het recht om inzage te vragen in de over u opgeslagen persoonsgegevens (artikel 15 AVG) en om rectificatie daarvan (artikel 16 AVG), wissing daarvan (artikel 17 AVG) of beperking van de verwerking (artikel 18 AVG) te verlangen; verder hebt u het recht om deze gegevens in een overdraagbaar formaat te ontvangen (artikel 20 AVG). Een gegeven toestemming kunt u op elk moment intrekken (artikel 7, lid 3, AVG). Neem hiervoor contact op met de hierboven genoemde verwerkingsverantwoordelijke.',
        'objection_clicks' => 'Tegen het registreren van klikken op korte links, dat op artikel 6, lid 1, onder f), AVG berust, kunt u bezwaar maken om redenen die verband houden met uw specifieke situatie (artikel 21 AVG).',
        'cookie_identifier' => 'Voor zover u via het cookie “mx_vid” wordt herkend, is de in dat cookie opgeslagen waarde het enige kenmerk waarmee de over u opgeslagen gegevens kunnen worden teruggevonden. U vindt die waarde in de cookie-instellingen van uw browser onder de naam “mx_vid”. Deel ons deze waarde samen met de betreffende website mee wanneer u inzage of wissing verlangt.',
        'anonymous' => 'Een kenmerk dat u ons zou kunnen voorleggen, bestaat niet bij :scope. Na afloop van de dagsleutel van 48 uur zijn de daarbij opgeslagen gegevens anoniem en kunnen zij niet meer aan een persoon worden gekoppeld. Het recht op inzage (artikel 15 AVG) en het recht op wissing (artikel 17 AVG) zijn op deze gegevens niet van toepassing, omdat wij u daarin niet kunnen identificeren en niet verplicht zijn om uitsluitend met dat doel aanvullende informatie te verzamelen (artikel 11 AVG).',
        'anonymous_scope_both' => 'het registreren van klikken op korte links en bij de websitemeting, voor zover die zonder cookie plaatsvindt',
        'anonymous_scope_clicks' => 'het registreren van klikken op korte links',
        'anonymous_scope_sites' => 'de websitemeting, voor zover die zonder cookie plaatsvindt',
        'complaint' => 'Los daarvan hebt u het recht om een klacht in te dienen bij een toezichthoudende autoriteit voor gegevensbescherming (artikel 77 AVG). Bevoegd is met name de toezichthoudende autoriteit van uw gewone verblijfplaats, van uw werkplek of van de plaats van de vermeende inbreuk.',
    ],

    // Alleen zichtbaar in de Marketix-interface, voor de beheerder — nooit
    // gepubliceerd. De tekst hierboven is bewust neutraal; dit blok mag
    // ronduit benoemen wat er mis is en wat dat raakt, vandaar het
    // duidelijk directere register, dat nooit in de gepubliceerde tekst
    // mag worden overgenomen.
    'warnings' => [
        'pixels_without_consent' => 'Retargeting-pixels zijn actief. Ze laden bij elke klik op de betreffende korte link, zonder dat ergens in het product toestemming wordt gevraagd. Dat is in strijd met § 25, lid 1, TDDDG, dat toestemming vereist voordat informatie op een eindapparaat wordt benaderd.',
        'cookie_without_consent' => 'De volgende websites plaatsen het cookie “mx_vid”, met een levensduur van 365 dagen, al bij het allereerste verzoek, voordat er toestemming is verkregen: :domains. Dit geldt ongeacht welke rechtsgrondslag voor deze tekst is gekozen — een gerechtvaardigd belang maakt een cookie die vóór elke toestemming wordt geplaatst niet rechtmatig.',
        'missing_party_details' => 'De volgende verplichte velden ontbreken en verschijnen als plaatshouders in de gepubliceerde tekst: :fields.',
        'consent_claimed_for_link_clicks' => 'Voor deze tekst is “toestemming” als rechtsgrondslag gekozen, maar het project telt klikken op korte links serverzijdig bij elk verzoek, zonder dat daarvoor toestemming kan worden verkregen. De gepubliceerde tekst noemt voor het registreren van klikken daarom in plaats daarvan een gerechtvaardigd belang — dat wijkt af van de gekozen grondslag.',

        'fields' => [
            'controller_name' => 'Naam van de verwerkingsverantwoordelijke',
            'controller_address' => 'Adres van de verwerkingsverantwoordelijke',
            'agency_name' => 'Naam van de verwerker',
            'agency_address' => 'Adres van de verwerker',
            'hosting_provider' => 'Hostingprovider',
            'hosting_location' => 'Locatie van de servers',
        ],
    ],

];
