<?php

namespace App\Enums;

enum ConsentMode: string
{
    case Immediate = 'immediate';
    case ThirdPartySignal = 'third_party_signal';
    case OwnBanner = 'own_banner';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => __('app.consent_mode_immediate'),
            self::ThirdPartySignal => __('app.consent_mode_third_party_signal'),
            self::OwnBanner => __('app.consent_mode_own_banner'),
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
