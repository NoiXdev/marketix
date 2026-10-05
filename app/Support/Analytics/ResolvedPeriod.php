<?php

namespace App\Support\Analytics;

final class ResolvedPeriod
{
    public function __construct(
        public readonly AnalyticsQuery $query,
        public readonly string $range,
        public readonly string $compare,
    ) {}

    public function comparison(): ?AnalyticsQuery
    {
        return match ($this->compare) {
            'year' => $this->query->previousYear(),
            'none' => null,
            default => $this->query->previous(),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $comparison = $this->comparison();

        return [
            'range' => $this->range,
            'from' => $this->query->from->toDateString(),
            'to' => $this->query->to->toDateString(),
            'days' => $this->query->days(),
            'interval' => $this->query->interval,
            'intervals' => AnalyticsQuery::allowedIntervals($this->query->days()),
            'compare' => $this->compare,
            'compare_from' => $comparison?->from->toDateString(),
            'compare_to' => $comparison?->to->toDateString(),
        ];
    }
}
