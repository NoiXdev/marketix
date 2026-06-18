<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public string $default_mailer;

    public string $from_address;

    public string $from_name;

    public string $postal_url;

    public string $postal_key;

    public string $smtp_host;

    public int $smtp_port;

    public string $smtp_username;

    public string $smtp_password;

    public string $smtp_scheme;

    public static function group(): string
    {
        return 'mail';
    }

    /**
     * @return array<int, string>
     */
    public static function encrypted(): array
    {
        return ['postal_key', 'smtp_password'];
    }
}
