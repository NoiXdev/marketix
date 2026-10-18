<?php

namespace App\Support\Privacy;

use App\Enums\ConsentMode;
use App\Enums\ControllerRole;
use App\Enums\LegalBasis;
use App\Enums\TrackingMode;

/**
 * Blunt, operator-facing warnings about configurations that are legally
 * risky. Unlike NoticeComposer, nothing here is ever published — it tells
 * the operator what to fix, so it may say plainly what is wrong instead of
 * describing it neutrally.
 */
final class NoticeWarnings
{
    /** @return list<array{key: string, message: string}> */
    public static function for(ProcessingSnapshot $snapshot, NoticeOptions $options): array
    {
        $warnings = [
            self::pixelsWithoutConsent($snapshot, $options),
            self::cookieWithoutConsent($snapshot, $options),
            self::missingPartyDetails($options),
            self::consentClaimedForLinkClicks($snapshot, $options),
        ];

        return array_values(array_filter(
            $warnings,
            fn (?array $warning) => $warning !== null,
        ));
    }

    /**
     * Pixels fire on every click with no consent gate anywhere in the
     * product, so § 25 TDDDG is violated the moment one is attached to a
     * link.
     */
    private static function pixelsWithoutConsent(ProcessingSnapshot $snapshot, NoticeOptions $options): ?array
    {
        if ($snapshot->pixelProviders() === []) {
            return null;
        }

        return [
            'key' => 'pixels_without_consent',
            'message' => trans('privacy_notice.warnings.pixels_without_consent', [], $options->locale),
        ];
    }

    /**
     * A site in `cookie` mode with `consent_mode: immediate` sets the
     * 365-day `mx_vid` cookie on the first request, before any consent is
     * obtained. This must fire regardless of the operator's chosen legal
     * basis: picking LegitimateInterest does not make a cookie set before
     * consent lawful, and the consent variant of the notice is not the only
     * place the problem shows up.
     */
    private static function cookieWithoutConsent(ProcessingSnapshot $snapshot, NoticeOptions $options): ?array
    {
        $domains = array_values(array_map(
            fn (SiteProcessing $site) => $site->domain,
            array_filter(
                $snapshot->sites(),
                fn (SiteProcessing $site) => $site->trackingMode === TrackingMode::Cookie
                    && $site->consentMode === ConsentMode::Immediate,
            ),
        ));

        if ($domains === []) {
            return null;
        }

        return [
            'key' => 'cookie_without_consent',
            'message' => trans('privacy_notice.warnings.cookie_without_consent', [
                'domains' => implode(', ', $domains),
            ], $options->locale),
        ];
    }

    /**
     * Which party fields are mandatory depends on the controller role: in
     * ControllerRole::Client the controller is the client and the agency
     * appears as a processor, so both parties' details are required. In
     * ControllerRole::OwnCompany the agency is itself the controller and
     * there is no separate client, so the agency fields are never asked
     * for and therefore never flagged as missing.
     */
    private static function missingPartyDetails(NoticeOptions $options): ?array
    {
        $fields = [
            'controller_name' => $options->controllerName,
            'controller_address' => $options->controllerAddress,
            'hosting_provider' => $options->hostingProvider,
            'hosting_location' => $options->hostingLocation,
        ];

        if ($options->role === ControllerRole::Client) {
            $fields['agency_name'] = $options->agencyName;
            $fields['agency_address'] = $options->agencyAddress;
        }

        $missing = [];

        foreach ($fields as $field => $value) {
            if ($value === null || trim($value) === '') {
                $missing[] = trans('privacy_notice.warnings.fields.'.$field, [], $options->locale);
            }
        }

        if ($missing === []) {
            return null;
        }

        return [
            'key' => 'missing_party_details',
            'message' => trans('privacy_notice.warnings.missing_party_details', [
                'fields' => implode(', ', $missing),
            ], $options->locale),
        ];
    }

    /**
     * Link clicks are recorded server-side on every click with no consent
     * mechanism anywhere in the product. When the operator picks
     * LegalBasis::Consent, NoticeComposer therefore states click recording
     * under Art. 6(1)(f) legitimate interest regardless, so the published
     * notice never claims a consent that was never obtained. That
     * divergence between what the operator chose and what the notice
     * actually says has to be visible to them.
     */
    private static function consentClaimedForLinkClicks(ProcessingSnapshot $snapshot, NoticeOptions $options): ?array
    {
        if ($options->legalBasis !== LegalBasis::Consent || $snapshot->linkCount() === 0) {
            return null;
        }

        return [
            'key' => 'consent_claimed_for_link_clicks',
            'message' => trans('privacy_notice.warnings.consent_claimed_for_link_clicks', [], $options->locale),
        ];
    }
}
