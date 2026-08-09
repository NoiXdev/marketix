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

    /**
     * Cases a user may currently pick. `OwnBanner` is reserved for v1.1
     * (the snippet has no own-banner branch yet), so it is hidden from the
     * UI and rejected by validation until then.
     *
     * @return array<int, self>
     */
    public static function selectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => $case !== self::OwnBanner,
        ));
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function selectableOptions(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::selectableCases(),
        );
    }

    /** @return array<int, string> */
    public static function selectableValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::selectableCases());
    }
}
