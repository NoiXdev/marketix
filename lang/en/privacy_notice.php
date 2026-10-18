<?php

/*
|--------------------------------------------------------------------------
| Privacy notice generator — English
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
| The key set and the `:token` placeholders per key are identical to the
| German file and are checked by NoticeTranslationsTest.
|
*/

return [

    // Visible stand-ins for party details that were left empty. They must
    // read as gaps in the text, never as prose.
    'placeholders' => [
        'controller_name' => '[Name of the controller]',
        'controller_address' => '[Address of the controller]',
        'agency_name' => '[Name of the processor]',
        'agency_address' => '[Address of the processor]',
        'hosting_provider' => '[Name of the hosting provider]',
        'hosting_location' => '[Location of the servers]',
    ],

    'roles' => [
        'own_company' => 'Own company',
        'client' => 'Client project',
    ],

    'legal_bases' => [
        'legitimate_interest' => 'Legitimate interest (Art. 6(1)(f) GDPR)',
        'consent' => 'Consent (Art. 6(1)(a) GDPR)',
    ],

    // Rendered in months when the configured retention divides evenly by
    // 30, otherwise in days.
    'duration' => [
        'months' => ':count month|:count months',
        'days' => ':count day|:count days',
    ],

    'controller' => [
        'heading' => 'Controller',
        'intro' => 'The controller for the processing described below within the meaning of Art. 4(7) GDPR is:',
        'party' => ':name, :address',
    ],

    'processors' => [
        'heading' => 'Processing on our behalf and hosting',
        'agency' => 'The measurement is carried out on our behalf and exclusively on our instructions by :name, :address, acting as a processor within the meaning of Art. 28 GDPR.',
        'own_company' => 'We operate the software used for the measurement ourselves. The measurement data is not analysed by any external service provider.',
        'hosting' => 'The servers on which the measurement data is stored are operated by :provider. Location of the servers: :location.',
        'transfer_pixels' => 'In addition, when a short link is opened, data is transmitted to the providers named under “Retargeting pixels”, predominantly to the United States of America.',
        'no_advertising_transfer' => 'The measurement data is not transmitted to third parties for advertising or marketing purposes.',
    ],

    'clicks' => [
        'heading' => 'Recording of clicks on short links',
        'intro' => 'For some of the links we distribute, short links are used that redirect to the actual target address when they are opened. Every request for such a short link is counted on the server; no cookie of our own is set for this.',
        'data' => 'The following is stored in the process: the hash value described under “Handling of the IP address”, country, country code and city, the language derived from the first two characters of your browser’s language setting, the full referring address (referrer), the browser used, the operating system, an indication of whether the request originates from an automated program, and the time of the request.',
    ],

    'analytics' => [
        'heading' => 'Statistical analysis of website use',
        'intro' => 'The use of the following websites is analysed statistically:',

        'site' => [
            'cookieless' => ':domain — The measurement takes place without cookies. Requests are attributed to one another solely by means of the hash value described under “Handling of the IP address”.',
            'hybrid' => ':domain — The measurement initially takes place without cookies. Only once consent has been given is the cookie “mx_vid” additionally set and the visit recognised by means of it.',
            'cookie_immediately' => ':domain — The cookie “mx_vid” is set on the very first request; further visits are recognised by means of it.',
            'cookie_after_consent' => ':domain — The cookie “mx_vid” is set as soon as this website’s consent tool signals consent; further visits are recognised by means of it.',
            'dnt' => 'If your browser sends the “Do Not Track” setting, no measurement takes place for this website.',
        ],

        'page_view' => 'Each time a page is opened, the following is stored: the path requested without query parameters, the page title, the host name, the full referring address (referrer) and its domain, country, country code, city and region, browser, operating system and device type, the language setting, the campaign parameters contained in the address requested (utm_source, utm_medium, utm_campaign and others), an indication of whether the request originates from an automated program, the active time on the page in seconds, the scroll depth reached, and the time of the request.',
        'visit' => 'For each coherent visit, the entry page, the exit page, whether it is a returning visit, and the campaign source of the first contact are stored in addition.',
        'outbound' => 'On the following websites, clicks on links leading to other websites are stored as an event together with the respective target address: :domains.',
        'downloads' => 'On the following websites, clicks on downloadable files are stored as an event together with the address of the file: :domains.',
        'site_search' => 'On the following websites, terms entered through the search function are stored as an event: :domains. They are converted to lower case and truncated after 100 characters.',
        'custom_events' => 'Further events may be recorded in addition. Such an event consists of a name and may contain additional information of up to 1 kilobyte; what that information is, is determined by whoever embedded the measurement code in the website.',
    ],

    'ip' => [
        'heading' => 'Handling of the IP address',
        'hash' => 'Your IP address is not stored. It is only processed transiently, in order to calculate from it, together with your browser’s identifier (user agent), a scope identifier and a random daily key, an irreversible hash value (SHA-256). Only this hash value is stored.',
        'salt' => 'The daily key is generated anew every day and deleted after 48 hours at the latest. After that, the stored hash value can no longer be attributed to any IP address; from that point on, the data concerned is anonymous.',
        'scope_both' => 'The scope identifier is the respective project for short links and the respective website for website measurement. The same person therefore receives different hash values in different projects and on different websites and cannot be recognised across them.',
        'scope_clicks' => 'The scope identifier is the respective project. The same person therefore receives different hash values in different projects and cannot be recognised across projects.',
        'scope_sites' => 'The scope identifier is the respective website. The same person therefore receives different hash values on different websites and cannot be recognised across websites.',
        'geolocation' => 'Country, region and city are determined by means of a database installed locally on the server (MaxMind GeoLite2-City). Your IP address is not transmitted to anyone for this purpose and does not leave the server.',
    ],

    'cookies' => [
        'heading' => 'Cookies',
        'properties' => 'A cookie named “mx_vid” is used for the website measurement. It is set by the respective website itself (first-party cookie), has a lifetime of 365 days, carries the SameSite=Lax setting and, on a request over HTTPS, is additionally marked as “Secure”.',
        'purpose' => 'The cookie contains nothing but a randomly generated identifier. It serves solely to recognise returning visits by the same person to the same website; it is not used for any analysis beyond that website.',
        'set_immediately' => 'On the following websites, the cookie is set on the very first request: :domains.',
        'set_after_consent' => 'On the following websites, the cookie is only set once consent has been given: :domains.',
        'control' => 'You can delete the cookie at any time in your browser settings, where you can also prevent cookies from being set in the first place.',
    ],

    // Cites no legal basis and makes no consent claim — on purpose.
    'pixels' => [
        'heading' => 'Retargeting pixels',
        'intro' => 'Marketing services are configured for some of the short links in use. When such a short link is opened, the redirect does not happen immediately: an intermediate page is delivered first, which loads the scripts of the providers named below and then forwards you to the target address.',
        'providers' => 'The services of the following providers are embedded in the process: :providers.',
        'transmission' => 'These scripts are delivered by the providers named and run in your browser. They set their own cookies and transmit data to the respective provider, including your IP address, your browser’s identifier and the address requested. The transmission is predominantly to the United States of America.',
        'vendor_notices' => 'The providers’ own privacy notices inform you about the processing carried out by them and about your rights in relation to them.',
    ],

    // Governs measurement only: link clicks and site analytics. The pixel
    // section is deliberately outside its scope, which is why the covered
    // activities are enumerated rather than implied.
    'legal_basis' => [
        'heading' => 'Legal basis',
        'activities_both' => 'the recording of clicks on short links and the statistical analysis of website use',
        'activities_clicks' => 'the recording of clicks on short links',
        'activities_sites' => 'the statistical analysis of website use',
        'legitimate_interest' => 'The legal basis for :activities is Art. 6(1)(f) GDPR.',
        'legitimate_interest_detail' => 'Our legitimate interest lies in being able to understand the reach and the use of our offering and to improve it on that basis.',
        'clicks_legitimate_interest' => 'The legal basis for the recording of clicks on short links is Art. 6(1)(f) GDPR. The count is performed on the server every time a short link is opened; our legitimate interest lies in being able to understand the reach of the links we distribute.',
        'consent' => 'The legal basis for :activities is your consent pursuant to Art. 6(1)(a) GDPR. In so far as information is stored on your device or read from it in the process, this is additionally based on Article 5(3) of the ePrivacy Directive (2002/58/EC) and its implementation in national law.',
        'consent_withdrawal' => 'You can withdraw consent you have given at any time with effect for the future. The lawfulness of the processing carried out up to the withdrawal remains unaffected.',
    ],

    'retention' => [
        'heading' => 'Storage period',
        'clicks' => 'The data stored about clicks on short links is deleted automatically and permanently once the storage period has elapsed. The storage period is :duration.',
        'sites_uniform' => 'The data stored as part of the website measurement is deleted automatically and permanently once the storage period has elapsed. The storage period is :duration.',
        'sites_intro' => 'The data stored as part of the website measurement is deleted automatically and permanently once the storage period has elapsed. The storage period is set per website:',
        'site' => ':domain — :duration.',
    ],

    'rights' => [
        'heading' => 'Your rights',
        'list_legitimate_interest' => 'You have the right to request access to the personal data stored about you (Art. 15 GDPR) and to request its rectification (Art. 16 GDPR), its erasure (Art. 17 GDPR) or the restriction of processing (Art. 18 GDPR). You may also object to the processing on grounds relating to your particular situation (Art. 21 GDPR). Please contact the controller named above for this purpose.',
        'list_consent' => 'You have the right to request access to the personal data stored about you (Art. 15 GDPR) and to request its rectification (Art. 16 GDPR), its erasure (Art. 17 GDPR) or the restriction of processing (Art. 18 GDPR); you also have the right to receive this data in a transferable format (Art. 20 GDPR). You can withdraw consent you have given at any time (Art. 7(3) GDPR). Please contact the controller named above for this purpose.',
        'objection_clicks' => 'You may object to the recording of clicks on short links, which is based on Art. 6(1)(f) GDPR, on grounds relating to your particular situation (Art. 21 GDPR).',
        'cookie_identifier' => 'In so far as you are recognised by means of the “mx_vid” cookie, the value stored in that cookie is the only identifying feature by which the data stored about you can be located. You will find it in your browser’s cookie settings under the name “mx_vid”. Please provide us with this value together with the website concerned when you request access or erasure.',
        'anonymous' => 'There is no identifying feature you could present to us in the case of :scope. Once the 48-hour daily key has expired, the data stored in that context is anonymous and can no longer be attributed to any person. The rights of access (Art. 15 GDPR) and to erasure (Art. 17 GDPR) do not apply to this data, because we cannot identify you within it and are not obliged to collect additional information for that purpose alone (Art. 11 GDPR).',
        'anonymous_scope_both' => 'the recording of clicks on short links and website measurement in so far as it takes place without a cookie',
        'anonymous_scope_clicks' => 'the recording of clicks on short links',
        'anonymous_scope_sites' => 'website measurement in so far as it takes place without a cookie',
        'complaint' => 'Irrespective of this, you have the right to lodge a complaint with a data protection supervisory authority (Art. 77 GDPR). The authority competent in particular is that of your habitual residence, of your place of work or of the place of the alleged infringement.',
    ],

];
