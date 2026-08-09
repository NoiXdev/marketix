<?php

namespace App\Enums;

enum GoalType: string
{
    case Event = 'event';
    case Pageview = 'pageview';

    public function label(): string
    {
        return match ($this) {
            self::Event => __('app.goal_type_event'),
            self::Pageview => __('app.goal_type_pageview'),
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
