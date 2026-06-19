<?php

use App\Providers\AppServiceProvider;
use App\Providers\BrandingServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\MailSettingsServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    MailSettingsServiceProvider::class,
    BrandingServiceProvider::class,
];
