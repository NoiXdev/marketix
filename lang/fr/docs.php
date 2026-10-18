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
            'referrer' => 'L’URL référente complète — l’adresse de la page depuis laquelle le visiteur est arrivé, pas seulement son domaine.',
            'utm' => 'Paramètres de campagne (balises UTM) présents sur le lien.',
            'timestamp' => 'La date et l’heure du clic ou de la vue de page.',
            'title_hostname' => 'Le titre de la page et le nom d’hôte de la page suivie.',
            'engagement' => 'Interactions sur les pages suivies : temps actif sur la page, profondeur de défilement ainsi que clics sur les liens sortants et téléchargements de fichiers (adresse cible uniquement, sans paramètres de requête).',
            'search' => 'Termes de recherche du site – uniquement si vous l’activez explicitement pour un site.',
        ],

        'modes' => [
            'heading' => 'Modes de suivi & Do-Not-Track',
            'cookieless' => 'Mode sans cookie (par défaut) : les visiteurs ne sont reconnus que par le hachage quotidien décrit ci-dessus — aucun cookie n’est stocké et les visiteurs ne peuvent pas être suivis d’un jour à l’autre.',
            'hybrid' => 'Mode hybride (optionnel, par site) : les visiteurs sont d’abord mesurés anonymement via le hachage quotidien. Dès qu’un signal de consentement est donné, le cookie first-party (mx_vid) prend le relais pour la reconnaissance ; seule la source de campagne de la visite anonyme précédente est alors conservée.',
            'cookie' => 'Mode cookie (optionnel, par site) : un cookie first-party (mx_vid) est stocké afin de reconnaître les visiteurs récurrents d’une visite à l’autre. Il n’est utilisé que si vous activez ce mode pour un site. Avec le réglage de consentement « Immédiat » (par défaut), il est posé sans signal de consentement préalable ; seul le réglage « Signal tiers » attend ce signal au préalable.',
            'dnt' => 'Do-Not-Track : chaque site peut être configuré pour respecter le signal « Do Not Track » du navigateur. Les visiteurs qui l’envoient ne sont pas suivis du tout.',
            'links_note' => 'Les statistiques de clics des liens courts sont toujours sans cookie et mesurées côté serveur — les modes cookie et hybride ne concernent que la fonctionnalité d’analyse de pages de :app.',
        ],

        'nocollect' => [
            'heading' => 'Ce que :app ne fait pas',
            'cookies' => 'Les projets sans pixel de retargeting associé ne déposent aucun cookie tiers et ne pratiquent aucun suivi inter-sites. En revanche, associer un pixel de retargeting (par ex. Google Ads, Meta/Facebook, LinkedIn) à un lien court fait charger par la page de redirection le script de ce fournisseur avant de rediriger le visiteur ; ce fournisseur dépose alors ses propres cookies tiers et reçoit des données — indépendamment de tout consentement. L’analyse first-party (hachage quotidien ou cookie mx_vid) n’est pas affectée par cela ; voir les modes de suivi ci-dessus pour leurs détails.',
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

        'responsibilities' => [
            'heading' => 'Vos responsabilités en tant que responsable du traitement',
            'intro' => 'Lorsque vous utilisez :app, vous êtes le responsable du traitement des données de vos visiteurs. En particulier, vous devez :',
            'policy' => 'Publier une politique de confidentialité qui révèle ce traitement analytique (le générateur ci-dessous en produit le texte).',
            'basis' => 'Vous assurer de disposer d’une base légale valide — une évaluation de l’intérêt légitime ou un consentement — pour votre région.',
            'consent' => 'Choisir un mode de suivi et un réglage de consentement adaptés à votre base légale — activez l’option Do-Not-Track et le consentement aux cookies lorsque votre région l’exige.',
            'dpa' => 'Conclure un accord de traitement des données (DPA / AVV) avec l’exploitant de votre instance :app, lorsque cela est requis.',
            'rights' => 'Traiter les demandes des personnes concernées. Les visiteurs n’étant identifiés que par un hachage quotidien non rattachable, la plupart des données stockées ne peuvent pas être reliées à une personne.',
        ],

        'generator' => [
            'heading' => 'Générateur de mentions de confidentialité',
            'intro' => 'Génère un texte à partir de ce que ce projet traite réellement. Rien n’est enregistré : le texte est recomposé à chaque modification.',
            'pointer' => 'Le texte destiné à votre propre politique de confidentialité est produit par le générateur plus bas sur cette page, adapté à ce que ce projet traite réellement.',

            'role' => [
                'label' => 'Qui est le responsable du traitement ?',
                'own_company' => [
                    'title' => 'Notre propre entreprise',
                    'text' => 'Vous mesurez pour votre propre entreprise. Il n’y a pas de client distinct.',
                ],
                'client' => [
                    'title' => 'Projet client',
                    'text' => 'Vous mesurez pour le compte d’un client. Le client est responsable du traitement et votre agence est sous-traitante.',
                ],
            ],

            'controller' => [
                'heading' => 'Responsable du traitement',
                'description' => 'Nommé dans le texte généré.',
                'name' => 'Nom',
                'address' => 'Adresse',
                'name_placeholder' => 'Exemple SARL',
                'address_placeholder' => 'Rue de l’Exemple 1, 75001 Paris',
                'locked' => 'Ces informations proviennent des réglages du projet et de l’exploitant ; seule l’administration de l’instance peut les modifier.',
            ],

            'processors' => [
                'heading' => 'Sous-traitants',
                'description' => 'Reprises des réglages de l’exploitant — affichées ici pour que vous voyiez ce que dira le texte.',
                'agency' => 'Agence',
                'hosting' => 'Hébergeur',
                'location' => 'Localisation des serveurs',
                'empty' => 'Non renseigné',
            ],

            'activities' => [
                'heading' => 'Ce qui est actif',
                'description' => 'Prérempli à partir de la configuration du projet. Désactivez ce que le texte ne doit pas mentionner.',
                'inactive' => 'Non détecté dans ce projet.',
                'link_clicks' => [
                    'title' => 'Statistiques de clics des liens courts',
                    'text' => 'Enregistrées côté serveur à chaque clic. Liens courts dans ce projet : :count',
                ],
                'site_analytics' => [
                    'title' => 'Analyse des sites',
                    'text' => 'Pages vues et visites sur les sites suivis.',
                ],
                'pixels' => [
                    'title' => 'Pixels de reciblage',
                    'text' => 'La page de redirection charge le script du fournisseur avant de rediriger. Le produit ne prévoit aucun recueil de consentement pour cela.',
                    'flag' => 'Sans consentement',
                    'providers' => 'Fournisseurs : :providers',
                    'links' => 'Liens courts concernés : :count',
                    'required' => 'Cette mention ne peut pas être désactivée : la transmission a lieu à chaque clic et doit figurer dans le texte.',
                ],
                'outbound' => [
                    'title' => 'Liens sortants et téléchargements',
                    'text' => 'Clics sur les liens externes et téléchargements de fichiers sur les sites suivis.',
                ],
                'site_search' => [
                    'title' => 'Recherche interne',
                    'text' => 'Termes saisis dans la recherche interne d’un site suivi.',
                ],
                'custom_events' => [
                    'title' => 'Événements personnalisés',
                    'text' => 'Événements que votre propre code envoie au traceur.',
                ],
            ],

            'legal_basis' => [
                'label' => 'Base légale',
                'legitimate_interest' => [
                    'title' => 'Intérêt légitime',
                    'text' => 'Art. 6, §1, f) RGPD — mesure d’audience sans consentement.',
                ],
                'consent' => [
                    'title' => 'Consentement',
                    'text' => 'Art. 6, §1, a) RGPD — la mesure ne démarre qu’après le consentement.',
                ],
            ],

            'language' => [
                'label' => 'Langue du texte généré',
                'hint' => 'Ne change que la langue du texte généré, pas celle de l’interface.',
            ],

            'warnings' => [
                'heading' => 'À vérifier avant publication',
            ],

            'preview' => [
                'heading' => 'Aperçu',
                'loading' => 'Génération…',
                'error' => 'Le texte n’a pas pu être généré. Veuillez réessayer.',
                'copy_text' => 'Copier en texte',
                'copy_html' => 'Copier en HTML',
                'copied' => 'Copié',
                'placeholders' => 'Encore à remplacer : :list',
            ],
        ],
    ],
];
