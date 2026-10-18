<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class OperatorSettings extends Settings
{
    public ?string $agency_name = null;

    public ?string $agency_address = null;

    public ?string $hosting_provider = null;

    public ?string $hosting_location = null;

    public static function group(): string
    {
        return 'operator';
    }
}
