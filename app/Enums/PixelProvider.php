<?php

namespace App\Enums;

enum PixelProvider: string
{
    case GoogleTagManager = 'google_tag_manager';
    case GoogleAnalytics  = 'google_analytics';
    case Facebook         = 'facebook';
    case GoogleAds        = 'google_ads';
    case LinkedIn         = 'linkedin';
    case Twitter          = 'twitter';
    case AdRoll           = 'adroll';
    case Quora            = 'quora';
    case Pinterest        = 'pinterest';
    case Bing             = 'bing';
    case Snapchat         = 'snapchat';
    case Reddit           = 'reddit';
    case TikTok           = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::GoogleTagManager => 'Google Tag Manager',
            self::GoogleAnalytics  => 'Google Analytics',
            self::Facebook         => 'Facebook',
            self::GoogleAds        => 'Google Ads',
            self::LinkedIn         => 'LinkedIn',
            self::Twitter          => 'Twitter',
            self::AdRoll           => 'AdRoll',
            self::Quora            => 'Quora',
            self::Pinterest        => 'Pinterest',
            self::Bing             => 'Bing',
            self::Snapchat         => 'Snapchat',
            self::Reddit           => 'Reddit',
            self::TikTok           => 'TikTok',
        };
    }

    public function tagLabel(): string
    {
        return match ($this) {
            self::GoogleTagManager => 'Container ID (e.g. GTM-XXXXXXX)',
            self::GoogleAnalytics  => 'Measurement ID (e.g. G-XXXXXXXXXX)',
            self::Facebook         => 'Pixel ID',
            self::GoogleAds        => 'Conversion ID (e.g. AW-XXXXXXXXX)',
            self::LinkedIn         => 'Partner ID',
            self::Twitter          => 'Pixel ID',
            self::AdRoll           => 'Advertiser ID',
            self::Quora            => 'Pixel ID',
            self::Pinterest        => 'Tag ID',
            self::Bing             => 'UET Tag ID',
            self::Snapchat         => 'Pixel ID',
            self::Reddit           => 'Pixel ID',
            self::TikTok           => 'Pixel ID',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
