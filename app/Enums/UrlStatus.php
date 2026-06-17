<?php

namespace App\Enums;

enum UrlStatus: int
{
    case DEACTIVATED = 0;
    case ACTIVATED = 1;

    public function label(): string
    {
        return match ($this) {
            self::DEACTIVATED => __('app.deactivated'),
            self::ACTIVATED => __('app.activated'),
        };
    }
}
