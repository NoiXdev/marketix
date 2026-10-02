<?php

namespace App\Enums;

enum WidgetType: string
{
    case Kpi = 'kpi';
    case Timeseries = 'timeseries';
    case TopList = 'top_list';
    case GeoMap = 'geo_map';
    case Activity = 'activity';
    case QuickActions = 'quick_actions';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
