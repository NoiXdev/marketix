<?php

use App\Providers\AppServiceProvider;
use App\Providers\BrandingServiceProvider;
use App\Providers\DemoServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\MailSettingsServiceProvider;
use App\Providers\StorageSettingsServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    MailSettingsServiceProvider::class,
    StorageSettingsServiceProvider::class,
    BrandingServiceProvider::class,
    DemoServiceProvider::class,
];
