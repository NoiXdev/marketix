<?php

/*
|--------------------------------------------------------------------------
| Privacy notice generator — French
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
| carrier sentence that begins with "de" or "dans le cas de", which is why
| the second limb of the `*_both` variants repeats the preposition.
|
| The key set and the `:token` placeholders per key are identical to the
| German file and are checked by NoticeTranslationsTest.
|
*/

return [

    // Visible stand-ins for party details that were left empty. They must
    // read as gaps in the text, never as prose.
    'placeholders' => [
        'controller_name' => '[Nom du responsable du traitement]',
        'controller_address' => '[Adresse du responsable du traitement]',
        'agency_name' => '[Nom du sous-traitant]',
        'agency_address' => '[Adresse du sous-traitant]',
        'hosting_provider' => '[Nom de l’hébergeur]',
        'hosting_location' => '[Emplacement des serveurs]',
    ],

    'roles' => [
        'own_company' => 'Notre propre entreprise',
        'client' => 'Projet client',
    ],

    'legal_bases' => [
        'legitimate_interest' => 'Intérêt légitime (article 6, paragraphe 1, point f), du RGPD)',
        'consent' => 'Consentement (article 6, paragraphe 1, point a), du RGPD)',
    ],

    // Rendered in months when the configured retention divides evenly by
    // 30, otherwise in days.
    'duration' => [
        'months' => ':count mois|:count mois',
        'days' => ':count jour|:count jours',
    ],

    'controller' => [
        'heading' => 'Responsable du traitement',
        'intro' => 'Le responsable du traitement décrit ci-après, au sens de l’article 4, point 7), du RGPD, est :',
        'party' => ':name, :address',
    ],

    'processors' => [
        'heading' => 'Sous-traitance et hébergement',
        'agency' => 'La mesure est effectuée pour notre compte et exclusivement sur nos instructions par :name, :address, en qualité de sous-traitant au sens de l’article 28 du RGPD.',
        'own_company' => 'Nous exploitons nous-mêmes le logiciel utilisé pour la mesure. Les données de mesure ne font l’objet d’aucune analyse par un prestataire externe.',
        'hosting' => 'Les serveurs sur lesquels les données de mesure sont conservées sont exploités par :provider. Emplacement des serveurs : :location.',
        'transfer_pixels' => 'En outre, lors de l’ouverture de liens courts, des données sont transmises aux fournisseurs mentionnés sous « Pixels de reciblage », principalement vers les États-Unis d’Amérique.',
        'no_advertising_transfer' => 'Les données de mesure ne sont transmises à aucun tiers à des fins publicitaires ou de marketing.',
    ],

    'clicks' => [
        'heading' => 'Enregistrement des clics sur les liens courts',
        'intro' => 'Pour une partie des liens que nous diffusons, nous utilisons des liens courts qui, lorsqu’ils sont ouverts, redirigent vers l’adresse de destination réelle. Chaque ouverture d’un tel lien court est comptabilisée côté serveur ; aucun cookie propre n’est déposé à cette fin.',
        'data' => 'Sont alors conservés : l’empreinte décrite sous « Traitement de l’adresse IP », le pays, le code du pays et la ville, la langue déduite des deux premiers caractères du paramètre linguistique de votre navigateur, l’adresse de provenance complète (référent), le navigateur utilisé, le système d’exploitation, une indication précisant si l’ouverture provient d’un programme automatisé, ainsi que le moment de l’ouverture.',
    ],

    'analytics' => [
        'heading' => 'Analyse statistique de l’utilisation des sites web',
        'intro' => 'L’utilisation des sites web suivants fait l’objet d’une analyse statistique :',

        'site' => [
            'cookieless' => ':domain — La mesure s’effectue sans cookies. Les ouvertures sont rattachées les unes aux autres uniquement au moyen de l’empreinte décrite sous « Traitement de l’adresse IP ».',
            'hybrid' => ':domain — La mesure s’effectue d’abord sans cookies. Ce n’est qu’une fois le consentement recueilli que le cookie « mx_vid » est également déposé et que la visite est reconnue par ce biais.',
            'cookie_immediately' => ':domain — Le cookie « mx_vid » est déposé dès la première ouverture ; les visites suivantes sont reconnues par ce biais.',
            'cookie_after_consent' => ':domain — Le cookie « mx_vid » est déposé dès que l’outil de gestion du consentement de ce site web signale un consentement ; les visites suivantes sont reconnues par ce biais.',
            'dnt' => 'Si votre navigateur transmet le réglage « Do Not Track », aucune mesure n’est effectuée pour ce site web.',
        ],

        'page_view' => 'À chaque page consultée sont conservés : le chemin appelé sans les paramètres de requête, le titre de la page, le nom d’hôte, l’adresse de provenance complète (référent) et son domaine, le pays, le code du pays, la ville et la région, le navigateur, le système d’exploitation et le type d’appareil, le paramètre linguistique, les paramètres de campagne contenus dans l’adresse appelée (utm_source, utm_medium, utm_campaign et autres), une indication précisant si l’ouverture provient d’un programme automatisé, la durée de présence active en secondes, la profondeur de défilement atteinte ainsi que le moment de l’ouverture.',
        'visit' => 'Pour chaque visite continue sont en outre conservées la page d’entrée, la page de sortie, l’indication s’il s’agit d’une visite récurrente et la source de campagne du premier contact.',
        'outbound' => 'Sur les sites web suivants, les clics sur des liens menant vers d’autres sites web sont enregistrés comme événement, avec l’adresse de destination correspondante : :domains.',
        'downloads' => 'Sur les sites web suivants, les clics sur des fichiers téléchargeables sont enregistrés comme événement, avec l’adresse du fichier : :domains.',
        'site_search' => 'Sur les sites web suivants, les termes saisis au moyen de la fonction de recherche sont enregistrés comme événement : :domains. Ils sont alors convertis en minuscules et tronqués au-delà de 100 caractères.',
        'custom_events' => 'D’autres événements peuvent par ailleurs être collectés. Un tel événement se compose d’un nom et peut contenir des informations supplémentaires d’une taille maximale de 1 kilooctet ; la nature de ces informations est déterminée par l’entité qui a intégré le code de mesure dans le site web.',
    ],

    'ip' => [
        'heading' => 'Traitement de l’adresse IP',
        'hash' => 'Votre adresse IP n’est pas conservée. Elle est traitée de manière purement éphémère, afin d’en calculer, avec l’identifiant de votre navigateur (user agent), un identifiant de périmètre et une clé quotidienne aléatoire, une empreinte non réversible (SHA-256). Seule cette empreinte est conservée.',
        'salt' => 'La clé quotidienne est régénérée chaque jour et supprimée au plus tard au bout de 48 heures. Ensuite, l’empreinte conservée ne peut plus être rattachée à aucune adresse IP ; les données concernées sont anonymes à compter de ce moment.',
        'scope_both' => 'L’identifiant de périmètre correspond, pour les liens courts, au projet concerné et, pour la mesure d’audience des sites web, au site web concerné. Une même personne obtient ainsi des empreintes différentes selon les projets et selon les sites web et ne peut donc pas être reconnue de l’un à l’autre.',
        'scope_clicks' => 'L’identifiant de périmètre correspond au projet concerné. Une même personne obtient ainsi des empreintes différentes selon les projets et ne peut donc pas être reconnue d’un projet à l’autre.',
        'scope_sites' => 'L’identifiant de périmètre correspond au site web concerné. Une même personne obtient ainsi des empreintes différentes selon les sites web et ne peut donc pas être reconnue d’un site web à l’autre.',
        'geolocation' => 'L’attribution du pays, de la région et de la ville s’effectue au moyen d’une base de données installée localement sur le serveur (MaxMind GeoLite2-City). Votre adresse IP n’est transmise à personne à cette fin et ne quitte pas le serveur.',
    ],

    'cookies' => [
        'heading' => 'Cookies',
        'properties' => 'Pour la mesure d’audience des sites web, un cookie portant le nom « mx_vid » est utilisé. Il est déposé par le site web lui-même (cookie interne), a une durée de vie de 365 jours, porte le réglage SameSite=Lax et est en outre marqué « Secure » lors d’un accès via HTTPS.',
        'purpose' => 'Le cookie contient uniquement un identifiant généré de manière aléatoire. Il sert exclusivement à reconnaître les visites répétées d’une même personne sur un même site web ; il ne donne lieu à aucune analyse au-delà de ce site web.',
        'set_immediately' => 'Sur les sites web suivants, le cookie est déposé dès la première ouverture : :domains.',
        'set_after_consent' => 'Sur les sites web suivants, le cookie n’est déposé qu’après le recueil d’un consentement : :domains.',
        'control' => 'Vous pouvez supprimer le cookie à tout moment dans les paramètres de votre navigateur et y empêcher également, d’emblée, le dépôt de cookies.',
    ],

    // Cites no legal basis and makes no consent claim — on purpose.
    'pixels' => [
        'heading' => 'Pixels de reciblage',
        'intro' => 'Des services marketing sont associés à une partie des liens courts utilisés. Lorsqu’un tel lien court est ouvert, la redirection n’est pas immédiate : une page intermédiaire est d’abord affichée, qui charge les scripts des fournisseurs indiqués ci-après puis vous redirige vers l’adresse de destination.',
        'providers' => 'Les services des fournisseurs suivants sont ainsi intégrés : :providers.',
        'transmission' => 'Ces scripts sont fournis par les fournisseurs mentionnés et s’exécutent dans votre navigateur. Ils déposent leurs propres cookies et transmettent des données au fournisseur concerné, dont votre adresse IP, l’identifiant de votre navigateur et l’adresse appelée. La transmission a lieu principalement vers les États-Unis d’Amérique.',
        'vendor_notices' => 'Les informations relatives au traitement effectué par les fournisseurs mentionnés et à vos droits à leur égard figurent dans leurs propres politiques de confidentialité.',
    ],

    // Governs measurement only: link clicks and site analytics. The pixel
    // section is deliberately outside its scope, which is why the covered
    // activities are enumerated rather than implied.
    'legal_basis' => [
        'heading' => 'Base juridique',
        'activities_both' => 'l’enregistrement des clics sur les liens courts et de l’analyse statistique de l’utilisation des sites web',
        'activities_clicks' => 'l’enregistrement des clics sur les liens courts',
        'activities_sites' => 'l’analyse statistique de l’utilisation des sites web',
        'legitimate_interest' => 'La base juridique de :activities est l’article 6, paragraphe 1, point f), du RGPD.',
        'legitimate_interest_detail' => 'Notre intérêt légitime consiste à pouvoir comprendre la portée et l’utilisation de notre offre et à l’améliorer sur cette base.',
        'clicks_legitimate_interest' => 'La base juridique de l’enregistrement des clics sur les liens courts est l’article 6, paragraphe 1, point f), du RGPD. Le comptage s’effectue côté serveur à chaque ouverture d’un lien court ; notre intérêt légitime consiste à pouvoir comprendre la portée des liens que nous diffusons.',
        'consent' => 'La base juridique de :activities est votre consentement au titre de l’article 6, paragraphe 1, point a), du RGPD. Dans la mesure où des informations sont à cette occasion stockées sur votre terminal ou lues depuis celui-ci, cela repose en outre sur l’article 5, paragraphe 3, de la directive 2002/58/CE (« vie privée et communications électroniques ») et sur sa transposition en droit national.',
        'consent_withdrawal' => 'Vous pouvez retirer à tout moment un consentement donné, avec effet pour l’avenir. La licéité du traitement effectué jusqu’au retrait n’en est pas affectée.',
    ],

    'retention' => [
        'heading' => 'Durée de conservation',
        'clicks' => 'Les données conservées au sujet des clics sur les liens courts sont supprimées automatiquement et définitivement à l’expiration de la durée de conservation. La durée de conservation est de :duration.',
        'sites_uniform' => 'Les données conservées dans le cadre de la mesure d’audience des sites web sont supprimées automatiquement et définitivement à l’expiration de la durée de conservation. La durée de conservation est de :duration.',
        'sites_intro' => 'Les données conservées dans le cadre de la mesure d’audience des sites web sont supprimées automatiquement et définitivement à l’expiration de la durée de conservation. La durée de conservation est définie pour chaque site web :',
        'site' => ':domain — :duration.',
    ],

    'rights' => [
        'heading' => 'Vos droits',
        'list_legitimate_interest' => 'Vous avez le droit d’obtenir l’accès aux données à caractère personnel conservées à votre sujet (article 15 du RGPD) ainsi que d’en demander la rectification (article 16 du RGPD), l’effacement (article 17 du RGPD) ou la limitation du traitement (article 18 du RGPD). Vous pouvez en outre vous opposer au traitement pour des raisons tenant à votre situation particulière (article 21 du RGPD). Adressez-vous pour cela au responsable du traitement mentionné ci-dessus.',
        'list_consent' => 'Vous avez le droit d’obtenir l’accès aux données à caractère personnel conservées à votre sujet (article 15 du RGPD) ainsi que d’en demander la rectification (article 16 du RGPD), l’effacement (article 17 du RGPD) ou la limitation du traitement (article 18 du RGPD) ; vous disposez par ailleurs du droit de recevoir ces données dans un format transférable (article 20 du RGPD). Vous pouvez retirer à tout moment un consentement donné (article 7, paragraphe 3, du RGPD). Adressez-vous pour cela au responsable du traitement mentionné ci-dessus.',
        'objection_clicks' => 'Vous pouvez vous opposer, pour des raisons tenant à votre situation particulière, à l’enregistrement des clics sur les liens courts, qui repose sur l’article 6, paragraphe 1, point f), du RGPD (article 21 du RGPD).',
        'cookie_identifier' => 'Dans la mesure où vous êtes reconnu au moyen du cookie « mx_vid », la valeur conservée dans ce cookie est le seul élément d’identification permettant de retrouver les données conservées à votre sujet. Vous la trouverez dans les paramètres de cookies de votre navigateur sous le nom « mx_vid ». Communiquez-nous cette valeur ainsi que le site web concerné lorsque vous demandez l’accès ou l’effacement.',
        'anonymous' => 'Il n’existe aucun élément d’identification que vous pourriez nous présenter dans le cas de :scope. À l’expiration de la clé quotidienne de 48 heures, les données alors conservées sont anonymes et ne peuvent plus être rattachées à une personne. Les droits d’accès (article 15 du RGPD) et à l’effacement (article 17 du RGPD) ne s’appliquent pas à ces données, car nous ne pouvons pas vous y identifier et ne sommes pas tenus de collecter des informations supplémentaires à cette seule fin (article 11 du RGPD).',
        'anonymous_scope_both' => 'l’enregistrement des clics sur les liens courts et de la mesure d’audience des sites web, pour autant que celle-ci s’effectue sans cookie',
        'anonymous_scope_clicks' => 'l’enregistrement des clics sur les liens courts',
        'anonymous_scope_sites' => 'la mesure d’audience des sites web, pour autant que celle-ci s’effectue sans cookie',
        'complaint' => 'Indépendamment de cela, vous avez le droit d’introduire une réclamation auprès d’une autorité de contrôle en matière de protection des données (article 77 du RGPD). Est notamment compétente l’autorité de contrôle de votre lieu de résidence habituelle, de votre lieu de travail ou du lieu où la violation présumée a été commise.',
    ],

    // Affiché uniquement dans l’interface Marketix, à l’opérateur — jamais
    // publié. Le texte ci-dessus est volontairement neutre ; ce bloc peut
    // dire clairement ce qui ne va pas et ce que cela concerne, d’où un
    // registre nettement plus direct, qu’il ne faut jamais recopier dans
    // le texte publié.
    'warnings' => [
        'pixels_without_consent' => 'Des pixels de reciblage sont actifs. Ils se chargent à chaque clic sur le lien court concerné, sans qu’aucun consentement ne soit recueilli nulle part dans le produit. Cela enfreint le § 25, paragraphe 1, du TDDDG, qui exige un consentement avant tout accès à des informations stockées sur un terminal.',
        'cookie_without_consent' => 'Les sites web suivants déposent le cookie « mx_vid », d’une durée de vie de 365 jours, dès la toute première requête, avant tout recueil de consentement : :domains. Cela vaut indépendamment de la base légale choisie pour ce texte — un intérêt légitime ne rend pas licite un cookie déposé avant tout consentement.',
        'missing_party_details' => 'Les champs obligatoires suivants sont manquants et apparaissent comme des espaces réservés dans le texte publié : :fields.',
        'consent_claimed_for_link_clicks' => '« Consentement » a été choisi comme base légale pour ce texte, mais le projet comptabilise les clics sur les liens courts côté serveur à chaque requête, sans aucun moyen de recueillir un consentement avant de le faire. Le texte publié mentionne donc l’intérêt légitime pour l’enregistrement des clics — ce qui diverge de la base choisie.',

        'fields' => [
            'controller_name' => 'Nom du responsable du traitement',
            'controller_address' => 'Adresse du responsable du traitement',
            'agency_name' => 'Nom du sous-traitant',
            'agency_address' => 'Adresse du sous-traitant',
            'hosting_provider' => 'Hébergeur',
            'hosting_location' => 'Localisation des serveurs',
        ],
    ],

];
