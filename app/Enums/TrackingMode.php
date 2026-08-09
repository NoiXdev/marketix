<?php

namespace App\Enums;

enum TrackingMode: string
{
    case Cookieless = 'cookieless';
    case Cookie = 'cookie';

    public function label(): string
    {
        return match ($this) {
            self::Cookieless => __('app.tracking_mode_cookieless'),
            self::Cookie => __('app.tracking_mode_cookie'),
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
