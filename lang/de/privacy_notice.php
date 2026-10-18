<?php

/*
|--------------------------------------------------------------------------
| Privacy notice generator — German reference catalog
|--------------------------------------------------------------------------
|
| This is the text a customer pastes into their own privacy policy, so
| every sentence has to match what the code actually does. Two rules are
| not negotiable:
|
|   - The `pixels` block cites no legal basis and makes no consent claim.
|     It describes what the redirect interstitial does, neutrally. The
|     assessment of that gap belongs in the UI warning block.
|   - Nothing here may promise a right that cannot be exercised. The
|     `rights` block says plainly where Art. 15 and Art. 17 do not apply.
|
| The other locales are translated from this file and must keep the same
| key set and the same `:token` placeholders per key.
|
*/

return [

    // Visible stand-ins for party details that were left empty. They must
    // read as gaps in the text, never as prose.
    'placeholders' => [
        'controller_name' => '[Name des Verantwortlichen]',
        'controller_address' => '[Anschrift des Verantwortlichen]',
        'agency_name' => '[Name des Auftragsverarbeiters]',
        'agency_address' => '[Anschrift des Auftragsverarbeiters]',
        'hosting_provider' => '[Name des Hosting-Anbieters]',
        'hosting_location' => '[Standort der Server]',
    ],

    'roles' => [
        'own_company' => 'Eigenes Unternehmen',
        'client' => 'Kundenprojekt',
    ],

    'legal_bases' => [
        'legitimate_interest' => 'Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO)',
        'consent' => 'Einwilligung (Art. 6 Abs. 1 lit. a DSGVO)',
    ],

    // Rendered in months when the configured retention divides evenly by
    // 30, otherwise in days.
    'duration' => [
        'months' => ':count Monat|:count Monate',
        'days' => ':count Tag|:count Tage',
    ],

    'controller' => [
        'heading' => 'Verantwortlicher',
        'intro' => 'Verantwortlich für die nachfolgend beschriebene Verarbeitung im Sinne von Art. 4 Nr. 7 DSGVO ist:',
        'party' => ':name, :address',
    ],

    'processors' => [
        'heading' => 'Auftragsverarbeitung und Hosting',
        'agency' => 'Die Messung wird in unserem Auftrag und ausschließlich nach unserer Weisung durchgeführt von :name, :address, als Auftragsverarbeiter im Sinne von Art. 28 DSGVO.',
        'own_company' => 'Die zur Messung eingesetzte Software betreiben wir selbst. Eine Auswertung der Messdaten durch einen externen Dienstleister findet nicht statt.',
        'hosting' => 'Die Server, auf denen die Messdaten gespeichert werden, betreibt :provider. Standort der Server: :location.',
        'transfer_pixels' => 'Darüber hinaus werden beim Aufruf von Kurzlinks Daten an die unter „Retargeting-Pixel“ genannten Anbieter übermittelt, überwiegend in die Vereinigten Staaten von Amerika.',
        'no_advertising_transfer' => 'Eine Übermittlung der Messdaten an Dritte zu Werbe- oder Marketingzwecken findet nicht statt.',
    ],

    'clicks' => [
        'heading' => 'Erfassung von Klicks auf Kurzlinks',
        'intro' => 'Für einen Teil der von uns verbreiteten Links werden Kurzlinks eingesetzt, die beim Aufruf auf die eigentliche Zieladresse weiterleiten. Jeder Aufruf eines solchen Kurzlinks wird serverseitig gezählt; ein eigenes Cookie wird dafür nicht gesetzt.',
        'data' => 'Gespeichert werden dabei: der unter „Umgang mit der IP-Adresse“ beschriebene Prüfwert, Land, Ländercode und Stadt, die aus den ersten beiden Zeichen der Spracheinstellung Ihres Browsers abgeleitete Sprache, die vollständige verweisende Adresse (Referrer), der verwendete Browser, das Betriebssystem, eine Kennzeichnung, ob der Aufruf von einem automatisierten Programm stammt, sowie der Zeitpunkt des Aufrufs.',
    ],

    'analytics' => [
        'heading' => 'Statistische Auswertung der Websitenutzung',
        'intro' => 'Die Nutzung der folgenden Websites wird statistisch ausgewertet:',

        'site' => [
            'cookieless' => ':domain — Die Messung erfolgt ohne Cookies. Aufrufe werden ausschließlich über den unter „Umgang mit der IP-Adresse“ beschriebenen Prüfwert einander zugeordnet.',
            'hybrid' => ':domain — Die Messung erfolgt zunächst ohne Cookies. Erst wenn eine Einwilligung vorliegt, wird zusätzlich das Cookie „mx_vid“ gesetzt und der Besuch darüber wiedererkannt.',
            'cookie_immediately' => ':domain — Bereits beim ersten Aufruf wird das Cookie „mx_vid“ gesetzt; weitere Besuche werden darüber wiedererkannt.',
            'cookie_after_consent' => ':domain — Das Cookie „mx_vid“ wird gesetzt, sobald das Einwilligungswerkzeug dieser Website eine Einwilligung signalisiert; weitere Besuche werden darüber wiedererkannt.',
            'dnt' => 'Sendet Ihr Browser die Einstellung „Do Not Track“, findet für diese Website keine Messung statt.',
        ],

        'page_view' => 'Bei jedem Seitenaufruf werden gespeichert: der aufgerufene Pfad ohne Abfrageparameter, der Seitentitel, der Hostname, die vollständige verweisende Adresse (Referrer) und deren Domain, Land, Ländercode, Stadt und Region, Browser, Betriebssystem und Gerätetyp, die Spracheinstellung, die in der aufgerufenen Adresse enthaltenen Kampagnenparameter (utm_source, utm_medium, utm_campaign und weitere), eine Kennzeichnung, ob der Aufruf von einem automatisierten Programm stammt, die aktive Verweildauer in Sekunden, die erreichte Scrolltiefe sowie der Zeitpunkt des Aufrufs.',
        'visit' => 'Je zusammenhängendem Besuch werden zusätzlich die Einstiegsseite, die Ausstiegsseite, die Angabe, ob es sich um einen wiederkehrenden Besuch handelt, und die Kampagnenquelle des ersten Kontakts gespeichert.',
        'outbound' => 'Auf den folgenden Websites werden Klicks auf Links, die auf andere Websites führen, zusammen mit der jeweiligen Zieladresse als Ereignis gespeichert: :domains.',
        'downloads' => 'Auf den folgenden Websites werden Klicks auf herunterladbare Dateien zusammen mit der Adresse der Datei als Ereignis gespeichert: :domains.',
        'site_search' => 'Auf den folgenden Websites werden Begriffe, die über die Suchfunktion eingegeben werden, als Ereignis gespeichert: :domains. Sie werden dabei in Kleinbuchstaben umgewandelt und nach 100 Zeichen abgeschnitten.',
        'custom_events' => 'Darüber hinaus können weitere Ereignisse erfasst werden. Ein solches Ereignis besteht aus einem Namen und kann zusätzliche Angaben von bis zu 1 Kilobyte enthalten; welche Angaben das sind, legt die Stelle fest, die den Messcode in die Website eingebunden hat.',
    ],

    'ip' => [
        'heading' => 'Umgang mit der IP-Adresse',
        'hash' => 'Ihre IP-Adresse wird nicht gespeichert. Sie wird ausschließlich flüchtig verarbeitet, um daraus gemeinsam mit der Kennung Ihres Browsers (User-Agent), einer Bereichskennung und einem zufälligen Tagesschlüssel einen nicht umkehrbaren Prüfwert (SHA-256) zu berechnen. Gespeichert wird allein dieser Prüfwert.',
        'salt' => 'Der Tagesschlüssel wird täglich neu erzeugt und spätestens nach 48 Stunden gelöscht. Danach lässt sich der gespeicherte Prüfwert keiner IP-Adresse mehr zuordnen; die betreffenden Daten sind ab diesem Zeitpunkt anonym.',
        'scope_both' => 'Die Bereichskennung ist bei Kurzlinks das jeweilige Projekt und bei der Websitemessung die jeweilige Website. Dieselbe Person erhält dadurch in verschiedenen Projekten und auf verschiedenen Websites unterschiedliche Prüfwerte und ist darüber nicht übergreifend wiedererkennbar.',
        'scope_clicks' => 'Die Bereichskennung ist das jeweilige Projekt. Dieselbe Person erhält dadurch in verschiedenen Projekten unterschiedliche Prüfwerte und ist darüber nicht projektübergreifend wiedererkennbar.',
        'scope_sites' => 'Die Bereichskennung ist die jeweilige Website. Dieselbe Person erhält dadurch auf verschiedenen Websites unterschiedliche Prüfwerte und ist darüber nicht websiteübergreifend wiedererkennbar.',
        'geolocation' => 'Die Zuordnung von Land, Region und Stadt erfolgt über eine lokal auf dem Server installierte Datenbank (MaxMind GeoLite2-City). Ihre IP-Adresse wird hierfür an niemanden übermittelt und verlässt den Server nicht.',
    ],

    'cookies' => [
        'heading' => 'Cookies',
        'properties' => 'Für die Websitemessung wird ein Cookie mit dem Namen „mx_vid“ verwendet. Es wird von der jeweiligen Website selbst gesetzt (First-Party-Cookie), hat eine Laufzeit von 365 Tagen, trägt die Einstellung SameSite=Lax und wird bei einem Aufruf über HTTPS zusätzlich als „Secure“ gekennzeichnet.',
        'purpose' => 'Das Cookie enthält ausschließlich eine zufällig erzeugte Kennung. Sie dient allein dazu, wiederkehrende Besuche derselben Person auf derselben Website zu erkennen; eine Auswertung über diese Website hinaus findet darüber nicht statt.',
        'set_immediately' => 'Auf den folgenden Websites wird das Cookie bereits beim ersten Aufruf gesetzt: :domains.',
        'set_after_consent' => 'Auf den folgenden Websites wird das Cookie erst gesetzt, nachdem eine Einwilligung vorliegt: :domains.',
        'control' => 'Sie können das Cookie jederzeit über die Einstellungen Ihres Browsers löschen und das Setzen von Cookies dort auch von vornherein unterbinden.',
    ],

    // Cites no legal basis and makes no consent claim — on purpose.
    'pixels' => [
        'heading' => 'Retargeting-Pixel',
        'intro' => 'Für einen Teil der eingesetzten Kurzlinks sind Marketing-Dienste hinterlegt. Wird ein solcher Kurzlink aufgerufen, erfolgt die Weiterleitung nicht unmittelbar: Es wird zunächst eine Zwischenseite ausgeliefert, die die Skripte der nachstehend genannten Anbieter lädt und Sie anschließend an die Zieladresse weiterleitet.',
        'providers' => 'Eingebunden werden dabei die Dienste folgender Anbieter: :providers.',
        'transmission' => 'Diese Skripte werden von den genannten Anbietern ausgeliefert und laufen in Ihrem Browser. Sie setzen eigene Cookies und übermitteln Daten an den jeweiligen Anbieter, darunter Ihre IP-Adresse, die Kennung Ihres Browsers und die aufgerufene Adresse. Die Übermittlung erfolgt überwiegend in die Vereinigten Staaten von Amerika.',
        'vendor_notices' => 'Über die Verarbeitung durch die genannten Anbieter und über Ihre Rechte ihnen gegenüber informieren deren eigene Datenschutzhinweise.',
    ],

    // Governs measurement only: link clicks and site analytics. The pixel
    // section is deliberately outside its scope, which is why the covered
    // activities are enumerated rather than implied.
    'legal_basis' => [
        'heading' => 'Rechtsgrundlage',
        'activities_both' => 'die Erfassung von Klicks auf Kurzlinks und die statistische Auswertung der Websitenutzung',
        'activities_clicks' => 'die Erfassung von Klicks auf Kurzlinks',
        'activities_sites' => 'die statistische Auswertung der Websitenutzung',
        'legitimate_interest' => 'Rechtsgrundlage für :activities ist Art. 6 Abs. 1 lit. f DSGVO.',
        'legitimate_interest_detail' => 'Unser berechtigtes Interesse besteht darin, die Reichweite und die Nutzung unseres Angebots nachvollziehen und es auf dieser Grundlage verbessern zu können.',
        'clicks_legitimate_interest' => 'Rechtsgrundlage für die Erfassung von Klicks auf Kurzlinks ist Art. 6 Abs. 1 lit. f DSGVO. Die Zählung erfolgt serverseitig bei jedem Aufruf eines Kurzlinks; unser berechtigtes Interesse besteht darin, die Reichweite der von uns verbreiteten Links nachvollziehen zu können.',
        'consent' => 'Rechtsgrundlage für :activities ist Ihre Einwilligung nach Art. 6 Abs. 1 lit. a DSGVO. Soweit dabei Informationen auf Ihrem Endgerät gespeichert oder ausgelesen werden, beruht dies zusätzlich auf § 25 Abs. 1 TDDDG.',
        'consent_withdrawal' => 'Sie können eine erteilte Einwilligung jederzeit mit Wirkung für die Zukunft widerrufen. Die Rechtmäßigkeit der bis zum Widerruf erfolgten Verarbeitung bleibt davon unberührt.',
    ],

    'retention' => [
        'heading' => 'Speicherdauer',
        'clicks' => 'Die zu Klicks auf Kurzlinks gespeicherten Daten werden nach Ablauf der Speicherdauer automatisch und endgültig gelöscht. Die Speicherdauer beträgt :duration.',
        'sites_uniform' => 'Die im Rahmen der Websitemessung gespeicherten Daten werden nach Ablauf der Speicherdauer automatisch und endgültig gelöscht. Die Speicherdauer beträgt :duration.',
        'sites_intro' => 'Die im Rahmen der Websitemessung gespeicherten Daten werden nach Ablauf der Speicherdauer automatisch und endgültig gelöscht. Die Speicherdauer ist je Website festgelegt:',
        'site' => ':domain — :duration.',
    ],

    'rights' => [
        'heading' => 'Ihre Rechte',
        'list_legitimate_interest' => 'Sie haben das Recht, Auskunft über die zu Ihnen gespeicherten personenbezogenen Daten zu verlangen (Art. 15 DSGVO) sowie deren Berichtigung (Art. 16 DSGVO), deren Löschung (Art. 17 DSGVO) oder die Einschränkung der Verarbeitung (Art. 18 DSGVO) zu verlangen. Außerdem können Sie der Verarbeitung aus Gründen, die sich aus Ihrer besonderen Situation ergeben, widersprechen (Art. 21 DSGVO). Wenden Sie sich hierzu an den oben genannten Verantwortlichen.',
        'list_consent' => 'Sie haben das Recht, Auskunft über die zu Ihnen gespeicherten personenbezogenen Daten zu verlangen (Art. 15 DSGVO) sowie deren Berichtigung (Art. 16 DSGVO), deren Löschung (Art. 17 DSGVO) oder die Einschränkung der Verarbeitung (Art. 18 DSGVO) zu verlangen; ferner haben Sie das Recht auf Herausgabe dieser Daten in einem übertragbaren Format (Art. 20 DSGVO). Eine erteilte Einwilligung können Sie jederzeit widerrufen (Art. 7 Abs. 3 DSGVO). Wenden Sie sich hierzu an den oben genannten Verantwortlichen.',
        'objection_clicks' => 'Der Erfassung von Klicks auf Kurzlinks, die auf Art. 6 Abs. 1 lit. f DSGVO beruht, können Sie aus Gründen, die sich aus Ihrer besonderen Situation ergeben, widersprechen (Art. 21 DSGVO).',
        'cookie_identifier' => 'Soweit Sie über das Cookie „mx_vid“ wiedererkannt werden, ist der in diesem Cookie gespeicherte Wert das einzige Merkmal, über das sich die zu Ihnen gespeicherten Daten auffinden lassen. Sie finden ihn in den Cookie-Einstellungen Ihres Browsers unter dem Namen „mx_vid“. Teilen Sie uns diesen Wert zusammen mit der betroffenen Website mit, wenn Sie Auskunft oder Löschung verlangen.',
        'anonymous' => 'Ein Merkmal, das Sie uns vorlegen könnten, gibt es nicht bei :scope. Nach Ablauf des Tagesschlüssels von 48 Stunden sind die dabei gespeicherten Daten anonym und lassen sich keiner Person mehr zuordnen. Die Rechte auf Auskunft (Art. 15 DSGVO) und auf Löschung (Art. 17 DSGVO) finden auf diese Daten keine Anwendung, weil wir Sie darin nicht identifizieren können und nicht verpflichtet sind, allein zu diesem Zweck zusätzliche Informationen zu erheben (Art. 11 DSGVO).',
        'anonymous_scope_both' => 'der Erfassung von Klicks auf Kurzlinks und bei der Websitemessung, soweit diese ohne Cookie erfolgt',
        'anonymous_scope_clicks' => 'der Erfassung von Klicks auf Kurzlinks',
        'anonymous_scope_sites' => 'der Websitemessung, soweit diese ohne Cookie erfolgt',
        'complaint' => 'Unabhängig davon steht Ihnen das Recht zu, sich bei einer Datenschutz-Aufsichtsbehörde zu beschweren (Art. 77 DSGVO). Zuständig ist insbesondere die Aufsichtsbehörde Ihres gewöhnlichen Aufenthaltsorts, Ihres Arbeitsplatzes oder des Orts des mutmaßlichen Verstoßes.',
    ],

    // Shown only in the Marketix UI, to the operator — never published. The
    // notice above is deliberately neutral; this block is allowed to say
    // plainly what is wrong and what it affects, so it is written in a
    // noticeably blunter register and must never be copied into the notice.
    'warnings' => [
        'pixels_without_consent' => 'Retargeting-Pixel sind aktiv. Sie laden bei jedem Klick auf den betroffenen Kurzlink, ohne dass irgendwo im Produkt eine Einwilligung eingeholt wird. Das verstößt gegen § 25 Abs. 1 TDDDG, der vor dem Zugriff auf Endgeräteinformationen eine Einwilligung verlangt.',
        'cookie_without_consent' => 'Folgende Websites setzen das Cookie „mx_vid“ mit einer Laufzeit von 365 Tagen bereits beim ersten Aufruf, ohne dass zuvor eine Einwilligung vorliegt: :domains. Das gilt unabhängig davon, welche Rechtsgrundlage für diesen Hinweis gewählt wurde — ein berechtigtes Interesse macht ein Cookie, das vor jeder Einwilligung gesetzt wird, nicht zulässig.',
        'missing_party_details' => 'Folgende Pflichtangaben fehlen und erscheinen im veröffentlichten Hinweis als Platzhalter: :fields.',
        'consent_claimed_for_link_clicks' => 'Für diesen Hinweis wurde „Einwilligung“ als Rechtsgrundlage gewählt, das Projekt zählt aber Klicks auf Kurzlinks serverseitig bei jedem Aufruf, ohne dass dafür eine Einwilligung eingeholt werden kann. Der veröffentlichte Hinweis nennt für die Klickerfassung deshalb stattdessen berechtigtes Interesse — das weicht von der getroffenen Auswahl ab.',

        'fields' => [
            'controller_name' => 'Name des Verantwortlichen',
            'controller_address' => 'Anschrift des Verantwortlichen',
            'agency_name' => 'Name des Auftragsverarbeiters',
            'agency_address' => 'Anschrift des Auftragsverarbeiters',
            'hosting_provider' => 'Name des Hosting-Anbieters',
            'hosting_location' => 'Standort der Server',
        ],
    ],

];
