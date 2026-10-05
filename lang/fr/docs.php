<?php

return [
    'privacy' => [
        'title' => 'Confidentialité des données',
        'subtitle' => 'Comment :app traite les données personnelles et ce dont vous avez besoin pour être conforme au RGPD (DSGVO).',
        'disclaimer' => 'Cette page fournit des indications pratiques pour vous aider à démarrer — il ne s’agit pas d’un conseil juridique. Pour une évaluation contraignante, consultez un professionnel qualifié en protection des données.',

        'collect' => [
            'heading' => 'Ce que :app collecte',
            'intro' => 'Lorsqu’un visiteur ouvre l’un de vos liens courts ou une page analytique suivie, :app enregistre un petit ensemble de données techniques afin de produire des statistiques agrégées :',
            'ip' => 'Adresse IP — jamais stockée en clair. Elle est hachée avec le navigateur et un sel quotidien aléatoire supprimé après 48 heures, de sorte que l’identifiant de visiteur obtenu ne peut plus ensuite être rattaché à une IP.',
            'geo' => 'Localisation approximative (pays, région, ville) déduite de l’IP au moment de la requête.',
            'device' => 'Type d’appareil, navigateur et système d’exploitation, déduits de l’en-tête User-Agent.',
            'referrer' => 'Domaine référent — le site depuis lequel le visiteur est arrivé.',
            'utm' => 'Paramètres de campagne (balises UTM) présents sur le lien.',
            'timestamp' => 'La date et l’heure du clic ou de la vue de page.',
            'engagement' => 'Interactions sur les pages suivies : temps actif sur la page, profondeur de défilement ainsi que clics sur les liens sortants et téléchargements de fichiers (adresse cible uniquement, sans paramètres de requête).',
            'search' => 'Termes de recherche du site – uniquement si vous l’activez explicitement pour un site.',
        ],

        'modes' => [
            'heading' => 'Modes de suivi & Do-Not-Track',
            'cookieless' => 'Mode sans cookie (par défaut) : les visiteurs ne sont reconnus que par le hachage quotidien décrit ci-dessus — aucun cookie n’est stocké et les visiteurs ne peuvent pas être suivis d’un jour à l’autre.',
            'cookie' => 'Mode cookie (optionnel, par site) : un cookie first-party (mx_vid) est stocké afin de reconnaître les visiteurs récurrents d’une visite à l’autre. Il n’est utilisé que si vous activez ce mode pour un site, et uniquement après que le signal de consentement du visiteur a été donné.',
            'dnt' => 'Do-Not-Track : chaque site peut être configuré pour respecter le signal « Do Not Track » du navigateur. Les visiteurs qui l’envoient ne sont pas suivis du tout.',
            'links_note' => 'Les statistiques de clics des liens courts sont toujours sans cookie et mesurées côté serveur — le mode cookie ne concerne que la fonctionnalité d’analyse de pages de :app.',
        ],

        'nocollect' => [
            'heading' => 'Ce que :app ne fait pas',
            'cookies' => 'Aucun cookie tiers et aucun suivi inter-sites. L’analyse est sans cookie par défaut ; un cookie first-party n’est utilisé que si vous passez un site en mode cookie, et uniquement avec consentement (voir les modes de suivi ci-dessus).',
            'raw_ip' => 'Les adresses IP ne sont jamais écrites en clair dans la base de données — il n’existe pas de colonne IP.',
            'pii' => 'Aucun nom, adresse e-mail ou contenu de formulaire n’est collecté par le suivi des clics et des statistiques.',
            'cross' => 'Vos données analytiques ne sont jamais vendues ni partagées avec des tiers à des fins publicitaires.',
        ],

        'retention' => [
            'heading' => 'Base légale & conservation',
            'basis' => 'Le traitement des clics et des statistiques repose généralement sur votre intérêt légitime à mesurer l’audience (art. 6, §1, f du RGPD) ou sur le consentement des visiteurs lorsque votre juridiction l’exige.',
            'stats' => 'Les statistiques de clics brutes sont conservées :stats mois, puis automatiquement purgées.',
            'analytics' => 'Les vues de pages et sessions brutes sont conservées :analytics mois (ou moins si un site définit une durée plus courte), puis purgées.',
            'aggregates' => 'Les compteurs et graphiques agrégés peuvent être conservés plus longtemps ; ils ne contiennent aucun identifiant personnel.',
        ],

        'notice' => [
            'heading' => 'Mention de confidentialité pour votre site',
            'intro' => 'Si vous utilisez :app pour suivre des liens ou des pages, informez vos visiteurs. Vous pouvez adapter le texte ci-dessous pour votre propre politique de confidentialité :',
            'snippet' => 'Ce site utilise :app pour mesurer l’audience de ses liens et de ses pages. Lorsque vous ouvrez un lien ou une page suivis, des données techniques (une adresse IP hachée, la localisation approximative, le navigateur, le système d’exploitation et le site référent) sont traitées pour produire des statistiques anonymes. Des cookies ne sont utilisés que si vous consentez à une mesure basée sur les cookies, et les données ne servent pas à vous identifier personnellement.',
            'copy' => 'Copier la mention',
            'copied' => 'Copié',
        ],

        'responsibilities' => [
            'heading' => 'Vos responsabilités en tant que responsable du traitement',
            'intro' => 'Lorsque vous utilisez :app, vous êtes le responsable du traitement des données de vos visiteurs. En particulier, vous devez :',
            'policy' => 'Publier une politique de confidentialité qui révèle ce traitement analytique (vous pouvez utiliser la mention ci-dessus).',
            'basis' => 'Vous assurer de disposer d’une base légale valide — une évaluation de l’intérêt légitime ou un consentement — pour votre région.',
            'consent' => 'Choisir un mode de suivi et un réglage de consentement adaptés à votre base légale — activez l’option Do-Not-Track et le consentement aux cookies lorsque votre région l’exige.',
            'dpa' => 'Conclure un accord de traitement des données (DPA / AVV) avec l’exploitant de votre instance :app, lorsque cela est requis.',
            'rights' => 'Traiter les demandes des personnes concernées. Les visiteurs n’étant identifiés que par un hachage quotidien non rattachable, la plupart des données stockées ne peuvent pas être reliées à une personne.',
        ],
    ],
];
