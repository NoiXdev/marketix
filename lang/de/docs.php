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
            'referrer' => 'Referrer-Domain – die Website, von der eine besuchende Person kam.',
            'utm' => 'Kampagnenparameter (UTM-Tags), die im Link enthalten sind.',
            'timestamp' => 'Datum und Uhrzeit des Klicks bzw. Seitenaufrufs.',
            'engagement' => 'Interaktionen auf getrackten Seiten: aktive Verweildauer, Scrolltiefe sowie Klicks auf externe Links und Datei-Downloads (nur die Zieladresse ohne Query-Parameter).',
            'search' => 'Suchbegriffe der Website-Suche – nur wenn Sie dies pro Website ausdrücklich aktivieren.',
        ],

        'modes' => [
            'heading' => 'Tracking-Modi & Do-Not-Track',
            'cookieless' => 'Cookieloser Modus (Standard): Besucher werden ausschließlich über den oben beschriebenen Tageshash erkannt – es werden keine Cookies gespeichert und Besucher können nicht von einem Tag auf den nächsten verfolgt werden.',
            'cookie' => 'Cookie-Modus (optional, pro Website): Ein First-Party-Cookie (mx_vid) wird gespeichert, damit wiederkehrende Besucher über mehrere Besuche hinweg erkannt werden können. Es wird nur verwendet, wenn Sie diesen Modus für eine Website aktivieren, und erst nachdem das Einwilligungssignal der besuchenden Person vorliegt.',
            'dnt' => 'Do-Not-Track: Jede Website kann so eingestellt werden, dass sie das „Do Not Track“-Signal des Browsers beachtet. Besucher, die es senden, werden überhaupt nicht getrackt.',
            'links_note' => 'Klickstatistiken von Kurzlinks sind immer cookielos und werden serverseitig erfasst – der Cookie-Modus betrifft nur die :app-Seiten-Analyse.',
        ],

        'nocollect' => [
            'heading' => 'Was :app nicht tut',
            'cookies' => 'Keine Third-Party-Cookies und kein seitenübergreifendes Tracking. Die Analyse ist standardmäßig cookielos; ein First-Party-Cookie wird nur verwendet, wenn Sie eine Website auf den Cookie-Modus umstellen, und nur mit Einwilligung (siehe Tracking-Modi oben).',
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

        'notice' => [
            'heading' => 'Datenschutzhinweis für Ihre Website',
            'intro' => 'Wenn Sie :app zum Tracken von Links oder Seiten nutzen, informieren Sie Ihre Besucher. Sie können den folgenden Text für Ihre eigene Datenschutzerklärung anpassen:',
            'snippet' => 'Diese Website nutzt :app zur Messung der Reichweite ihrer Links und Seiten. Wenn Sie einen getrackten Link oder eine getrackte Seite öffnen, werden technische Daten (eine gehashte IP-Adresse, der ungefähre Standort, Browser, Betriebssystem und die verweisende Website) verarbeitet, um anonyme Statistiken zu erstellen. Cookies werden nur verwendet, wenn Sie einer cookiebasierten Messung zustimmen, und die Daten werden nicht dazu verwendet, Sie persönlich zu identifizieren.',
            'copy' => 'Hinweis kopieren',
            'copied' => 'Kopiert',
        ],

        'responsibilities' => [
            'heading' => 'Ihre Pflichten als Verantwortlicher',
            'intro' => 'Wenn Sie :app einsetzen, sind Sie der Verantwortliche für die Daten Ihrer Besucher. Insbesondere sollten Sie:',
            'policy' => 'Eine Datenschutzerklärung veröffentlichen, die diese Analyse-Verarbeitung offenlegt (Sie können den obigen Hinweis verwenden).',
            'basis' => 'Sicherstellen, dass Sie eine gültige Rechtsgrundlage haben – eine Abwägung des berechtigten Interesses oder eine Einwilligung – passend zu Ihrer Region.',
            'consent' => 'Einen Tracking-Modus und eine Einwilligungseinstellung wählen, die zu Ihrer Rechtsgrundlage passen – aktivieren Sie die Do-Not-Track-Option und die Cookie-Einwilligung, wo Ihre Region dies verlangt.',
            'dpa' => 'Einen Auftragsverarbeitungsvertrag (AVV) mit dem Betreiber Ihrer :app-Instanz abschließen, sofern erforderlich.',
            'rights' => 'Betroffenenanfragen bearbeiten. Da Besucher nur über einen täglich wechselnden, nicht zuordenbaren Hash identifiziert werden, lassen sich die meisten gespeicherten Daten keiner Einzelperson zuordnen.',
        ],
    ],
];
