<?php

return [
    'privacy' => [
        'title' => 'Datenschutz',
        'subtitle' => 'Wie :app personenbezogene Daten verarbeitet und was Sie für die DSGVO-Konformität benötigen.',
        'disclaimer' => 'Diese Seite bietet praktische Hilfestellung für den Einstieg – sie stellt keine Rechtsberatung dar. Für eine verbindliche Bewertung wenden Sie sich an eine qualifizierte Datenschutzberatung.',

        'collect' => [
            'heading' => 'Welche Daten :app erfasst',
            'intro' => 'Wenn eine besuchende Person einen Ihrer Kurzlinks oder eine getrackte Analytics-Seite öffnet, erfasst :app wenige technische Daten, um aggregierte Statistiken zu erstellen:',
            'ip' => 'IP-Adresse – wird niemals im Klartext gespeichert. Sie wird zusammen mit dem Browser und einem zufälligen Tagessalt gehasht, der nach 48 Stunden gelöscht wird, sodass die daraus entstehende Besucherkennung anschließend nicht mehr einer IP zugeordnet werden kann.',
            'geo' => 'Ungefährer Standort (Land, Region, Stadt), der zum Zeitpunkt der Anfrage aus der IP abgeleitet wird.',
            'device' => 'Gerätetyp, Browser und Betriebssystem, aus dem User-Agent ermittelt.',
            'referrer' => 'Die vollständige verweisende URL – die Adresse der Seite, von der eine besuchende Person kam, nicht nur deren Domain.',
            'utm' => 'Kampagnenparameter (UTM-Tags), die im Link enthalten sind.',
            'timestamp' => 'Datum und Uhrzeit des Klicks bzw. Seitenaufrufs.',
            'title_hostname' => 'Seitentitel und Hostname der getrackten Seite.',
            'engagement' => 'Interaktionen auf getrackten Seiten: aktive Verweildauer, Scrolltiefe sowie Klicks auf externe Links und Datei-Downloads (nur die Zieladresse ohne Query-Parameter).',
            'search' => 'Suchbegriffe der Website-Suche – nur wenn Sie dies pro Website ausdrücklich aktivieren.',
        ],

        'modes' => [
            'heading' => 'Tracking-Modi & Do-Not-Track',
            'cookieless' => 'Cookieloser Modus (Standard): Besucher werden ausschließlich über den oben beschriebenen Tageshash erkannt – es werden keine Cookies gespeichert und Besucher können nicht von einem Tag auf den nächsten verfolgt werden.',
            'hybrid' => 'Hybrid-Modus (optional, pro Website): Besucher werden zunächst anonym über den Tageshash gemessen. Liegt ein Einwilligungssignal vor, übernimmt das First-Party-Cookie (mx_vid) die Wiedererkennung; dabei wird aus dem vorherigen anonymen Besuch ausschließlich die Kampagnenquelle übernommen.',
            'cookie' => 'Cookie-Modus (optional, pro Website): Ein First-Party-Cookie (mx_vid) wird gespeichert, damit wiederkehrende Besucher über mehrere Besuche hinweg erkannt werden können. Es wird nur verwendet, wenn Sie diesen Modus für eine Website aktivieren. Mit der Einwilligungseinstellung „Sofort“ (Standard) wird es ohne vorheriges Einwilligungssignal gesetzt; nur mit der Einstellung „Drittanbieter-Signal“ wartet es, bis dieses Signal vorliegt.',
            'dnt' => 'Do-Not-Track: Jede Website kann so eingestellt werden, dass sie das „Do Not Track“-Signal des Browsers beachtet. Besucher, die es senden, werden überhaupt nicht getrackt.',
            'links_note' => 'Klickstatistiken von Kurzlinks sind immer cookielos und werden serverseitig erfasst – der Cookie- und Hybrid-Modus betreffen nur die :app-Seiten-Analyse.',
        ],

        'nocollect' => [
            'heading' => 'Was :app nicht tut',
            'cookies' => 'Projekte ohne verknüpfte Retargeting-Pixel setzen keine Third-Party-Cookies und betreiben kein seitenübergreifendes Tracking. Verknüpfen Sie jedoch ein Retargeting-Pixel (z. B. Google Ads, Meta/Facebook, LinkedIn) mit einem Kurzlink, lädt die Weiterleitungsseite das Skript des jeweiligen Anbieters, bevor sie weiterleitet; dieser Anbieter setzt dann seine eigenen Third-Party-Cookies und erhält Daten – unabhängig von einer Einwilligung. Die First-Party-Analyse (Tageshash bzw. mx_vid-Cookie) bleibt davon unberührt; siehe Tracking-Modi oben für deren Details.',
            'raw_ip' => 'IP-Adressen werden niemals im Klartext in der Datenbank gespeichert – es gibt keine IP-Spalte.',
            'pii' => 'Namen, E-Mail-Adressen oder Formularinhalte werden durch das Klick- und Analytics-Tracking nicht erfasst.',
            'cross' => 'Ihre Analytics-Daten werden niemals verkauft oder zu Werbezwecken an Dritte weitergegeben.',
        ],

        'retention' => [
            'heading' => 'Rechtsgrundlage & Speicherdauer',
            'basis' => 'Die Verarbeitung von Klick- und Analytics-Daten stützt sich in der Regel auf Ihr berechtigtes Interesse an der Reichweitenmessung (Art. 6 Abs. 1 lit. f DSGVO) oder auf die Einwilligung der Besucher, sofern Ihre Rechtsordnung dies verlangt.',
            'stats' => 'Rohe Klickstatistiken werden :stats Monate aufbewahrt und danach automatisch gelöscht.',
            'analytics' => 'Rohe Seitenaufruf- und Sitzungsdaten werden :analytics Monate aufbewahrt (oder kürzer, wenn eine Website eine kürzere Frist festlegt) und danach gelöscht.',
            'aggregates' => 'Aggregierte Zählwerte und Diagramme können länger aufbewahrt werden; sie enthalten keine personenbezogenen Kennungen.',
        ],

        'responsibilities' => [
            'heading' => 'Ihre Pflichten als Verantwortlicher',
            'intro' => 'Wenn Sie :app einsetzen, sind Sie der Verantwortliche für die Daten Ihrer Besucher. Insbesondere sollten Sie:',
            'policy' => 'Eine Datenschutzerklärung veröffentlichen, die diese Analyse-Verarbeitung offenlegt (den Text dafür erzeugt der Generator weiter unten).',
            'basis' => 'Sicherstellen, dass Sie eine gültige Rechtsgrundlage haben – eine Abwägung des berechtigten Interesses oder eine Einwilligung – passend zu Ihrer Region.',
            'consent' => 'Einen Tracking-Modus und eine Einwilligungseinstellung wählen, die zu Ihrer Rechtsgrundlage passen – aktivieren Sie die Do-Not-Track-Option und die Cookie-Einwilligung, wo Ihre Region dies verlangt.',
            'dpa' => 'Einen Auftragsverarbeitungsvertrag (AVV) mit dem Betreiber Ihrer :app-Instanz abschließen, sofern erforderlich.',
            'rights' => 'Betroffenenanfragen bearbeiten. Da Besucher nur über einen täglich wechselnden, nicht zuordenbaren Hash identifiziert werden, lassen sich die meisten gespeicherten Daten keiner Einzelperson zuordnen.',
        ],

        'generator' => [
            'heading' => 'Datenschutzhinweis-Generator',
            'intro' => 'Erzeugt einen Hinweistext aus dem, was dieses Projekt tatsächlich verarbeitet. Nichts davon wird gespeichert – der Text wird bei jeder Änderung neu zusammengestellt.',
            'pointer' => 'Den Text für Ihre eigene Datenschutzerklärung erzeugt der Generator weiter unten auf dieser Seite – zugeschnitten auf das, was dieses Projekt tatsächlich verarbeitet.',

            'role' => [
                'label' => 'Wer ist der Verantwortliche?',
                'own_company' => [
                    'title' => 'Eigenes Unternehmen',
                    'text' => 'Sie messen für Ihr eigenes Unternehmen. Es gibt keinen separaten Auftraggeber.',
                ],
                'client' => [
                    'title' => 'Kundenprojekt',
                    'text' => 'Sie messen im Auftrag eines Kunden. Der Kunde ist verantwortlich, Ihre Agentur ist Auftragsverarbeiterin.',
                ],
            ],

            'controller' => [
                'heading' => 'Verantwortlicher',
                'description' => 'Wird im Hinweistext namentlich genannt.',
                'name' => 'Name',
                'address' => 'Anschrift',
                'name_placeholder' => 'Musterfirma GmbH',
                'address_placeholder' => 'Musterstraße 1, 10115 Berlin',
                'locked' => 'Diese Angaben stammen aus den Projekt- und Betreibereinstellungen und können nur von der Instanz-Administration geändert werden.',
            ],

            'processors' => [
                'heading' => 'Auftragsverarbeiter',
                'description' => 'Aus den Betreibereinstellungen übernommen – hier nur zur Ansicht, damit Sie sehen, was im Hinweistext steht.',
                'agency' => 'Agentur',
                'hosting' => 'Hosting-Anbieter',
                'location' => 'Serverstandort',
                'empty' => 'Nicht hinterlegt',
            ],

            'activities' => [
                'heading' => 'Was aktiv ist',
                'description' => 'Aus der Projektkonfiguration vorbelegt. Schalten Sie ab, was der Hinweistext nicht erwähnen soll.',
                'inactive' => 'In diesem Projekt nicht erkannt.',
                'link_clicks' => [
                    'title' => 'Klickstatistiken für Kurzlinks',
                    'text' => 'Serverseitig bei jedem Klick erfasst. Kurzlinks in diesem Projekt: :count',
                ],
                'site_analytics' => [
                    'title' => 'Website-Analyse',
                    'text' => 'Seitenaufrufe und Besuche auf den getrackten Websites.',
                ],
                'pixels' => [
                    'title' => 'Retargeting-Pixel',
                    'text' => 'Die Weiterleitungsseite lädt das Skript des Anbieters, bevor sie weiterleitet. Eine Einwilligungsabfrage gibt es dafür im Produkt nicht.',
                    'flag' => 'Ohne Einwilligung',
                    'providers' => 'Anbieter: :providers',
                    'links' => 'Betroffene Kurzlinks: :count',
                    'required' => 'Diese Angabe lässt sich nicht abwählen: Die Übermittlung findet bei jedem Klick statt und muss im Hinweistext stehen.',
                ],
                'outbound' => [
                    'title' => 'Externe Links und Downloads',
                    'text' => 'Klicks auf externe Links und Datei-Downloads auf getrackten Websites.',
                ],
                'site_search' => [
                    'title' => 'Website-Suche',
                    'text' => 'Suchbegriffe, die in die Suche einer getrackten Website eingegeben werden.',
                ],
                'custom_events' => [
                    'title' => 'Eigene Events',
                    'text' => 'Events, die Ihr eigener Code an den Tracker sendet.',
                ],
            ],

            'legal_basis' => [
                'label' => 'Rechtsgrundlage',
                'legitimate_interest' => [
                    'title' => 'Berechtigtes Interesse',
                    'text' => 'Art. 6 Abs. 1 lit. f DSGVO – Reichweitenmessung ohne Einwilligung.',
                ],
                'consent' => [
                    'title' => 'Einwilligung',
                    'text' => 'Art. 6 Abs. 1 lit. a DSGVO – die Messung läuft erst nach einer Einwilligung.',
                ],
            ],

            'language' => [
                'label' => 'Sprache des Hinweistextes',
                'hint' => 'Ändert nur die Sprache des erzeugten Textes, nicht die der Oberfläche.',
            ],

            'warnings' => [
                'heading' => 'Vor der Veröffentlichung prüfen',
            ],

            'preview' => [
                'heading' => 'Vorschau',
                'loading' => 'Wird erzeugt …',
                'error' => 'Der Hinweistext konnte nicht erzeugt werden. Bitte versuchen Sie es erneut.',
                'copy_text' => 'Als Text kopieren',
                'copy_html' => 'Als HTML kopieren',
                'copied' => 'Kopiert',
                'placeholders' => 'Noch zu ersetzen: :list',
            ],
        ],
    ],
];
