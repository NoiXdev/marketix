<?php

namespace App\Enums;

enum CrawlMode: string
{
    case FullSite = 'full_site';
    case SinglePage = 'single_page';

    public function label(): string
    {
        return match ($this) {
            self::FullSite => __('crawler.mode_full_site'),
            self::SinglePage => __('crawler.mode_single_page'),
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
