<?php

namespace App\Enums;

/**
 * The legal basis a generated privacy notice cites for measurement — link
 * click statistics and site analytics. Retargeting pixels are deliberately
 * not covered by this choice; their section cites no basis at all.
 */
enum LegalBasis: string
{
    case LegitimateInterest = 'legitimate_interest';
    case Consent = 'consent';

    public function label(): string
    {
        return match ($this) {
            self::LegitimateInterest => __('privacy_notice.legal_bases.legitimate_interest'),
            self::Consent => __('privacy_notice.legal_bases.consent'),
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
