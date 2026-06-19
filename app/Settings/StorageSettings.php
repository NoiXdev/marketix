<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StorageSettings extends Settings
{
    public string $driver;

    public string $s3_key;

    public string $s3_secret;

    public string $s3_region;

    public string $s3_bucket;

    public string $s3_endpoint;

    public bool $s3_use_path_style;

    public static function group(): string
    {
        return 'storage';
    }

    /**
     * @return array<int, string>
     */
    public static function encrypted(): array
    {
        return ['s3_secret'];
    }
}
