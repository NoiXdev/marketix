<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;

final class ResolvedPeriod
{
    private const CALENDAR_RANGES = ['month', 'last_month', 'year', '12m'];

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
            default => in_array($this->range, self::CALENDAR_RANGES, true) ? $this->query->previous() : $this->query->previousMatchingWeekdays(),
        };
    }

    public function comparisonComplete(?CarbonImmutable $trackingSince): bool
    {
        $comparison = $this->comparison();
        if ($comparison === null || $trackingSince === null) {
            return true;
        }

        return $trackingSince->setTimezone($comparison->timezone())->startOfDay()->lte($comparison->from->startOfDay());
    }

    /** @return array<string, mixed> */
    public function toArray(?CarbonImmutable $trackingSince = null): array
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
            'compare_complete' => $this->comparisonComplete($trackingSince),
            'tracking_since' => $trackingSince?->setTimezone($this->query->timezone())->toDateString(),
            'current_bucket' => $this->query->endsBeforeDayEnd() ? $this->query->bucketKey($this->query->to) : null,
        ];
    }
}
