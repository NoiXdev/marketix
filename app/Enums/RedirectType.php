<?php

namespace App\Enums;

enum RedirectType: int
{
    case REDIRECT = 0;

    public function label(): string
    {
        return match ($this) {
            self::REDIRECT => 'Redirect',
        };
    }
}
