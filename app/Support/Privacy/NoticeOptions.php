<?php

namespace App\Support\Privacy;

use App\Enums\ControllerRole;
use App\Enums\LegalBasis;

/**
 * The answers the generator form supplies alongside a ProcessingSnapshot:
 * who the controller is, which legal basis the measurement runs on, which
 * language the notice is written in, the party details, and the activities
 * the user switched off. An immutable value object — no behaviour.
 */
final class NoticeOptions
{
    public const ACTIVITY_LINK_CLICKS = 'link_clicks';

    public const ACTIVITY_SITE_ANALYTICS = 'site_analytics';

    public const ACTIVITY_PIXELS = 'pixels';

    public const ACTIVITY_OUTBOUND_LINKS = 'outbound_links';

    public const ACTIVITY_FILE_DOWNLOADS = 'file_downloads';

    public const ACTIVITY_CONTACT_LINKS = 'contact_links';

    public const ACTIVITY_SITE_SEARCH = 'site_search';

    public const ACTIVITY_CUSTOM_EVENTS = 'custom_events';

    /**
     * Every activity the form can switch off. The composer reads these keys
     * out of $disabledActivities; anything not listed here is ignored.
     *
     * @var list<string>
     */
    public const ACTIVITIES = [
        self::ACTIVITY_LINK_CLICKS,
        self::ACTIVITY_SITE_ANALYTICS,
        self::ACTIVITY_PIXELS,
        self::ACTIVITY_OUTBOUND_LINKS,
        self::ACTIVITY_FILE_DOWNLOADS,
        self::ACTIVITY_CONTACT_LINKS,
        self::ACTIVITY_SITE_SEARCH,
        self::ACTIVITY_CUSTOM_EVENTS,
    ];

    /**
     * @param  list<string>  $disabledActivities  keys of activities the user switched off
     */
    public function __construct(
        public readonly ControllerRole $role,
        public readonly LegalBasis $legalBasis,
        public readonly string $locale,
        public readonly ?string $controllerName,
        public readonly ?string $controllerAddress,
        public readonly ?string $agencyName,
        public readonly ?string $agencyAddress,
        public readonly ?string $hostingProvider,
        public readonly ?string $hostingLocation,
        public readonly array $disabledActivities,
    ) {}

    public function wants(string $activity): bool
    {
        return ! in_array($activity, $this->disabledActivities, true);
    }
}
