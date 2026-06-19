<?php

namespace App\Reports;

use App\Enums\ReportFrequency;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

// All periods this produces are PREVIOUS full calendar windows, so their
// end is always in the past. ReportDateRange::custom() rejects a future
// end — do not introduce a "current period" window without revisiting that.
class ReportPeriod
{
    private function __construct(
        private readonly ReportFrequency $frequency,
        private readonly ReportDateRange $current,
        private readonly ReportDateRange $previous,
        private readonly string $label,
    ) {}

    public static function for(ReportFrequency $frequency, CarbonImmutable $now): self
    {
        return match ($frequency) {
            ReportFrequency::Daily => self::daily($now),
            ReportFrequency::Weekly => self::weekly($now),
            ReportFrequency::Monthly => self::monthly($now),
            ReportFrequency::Off => throw new InvalidArgumentException('ReportFrequency::Off has no reporting period.'),
        };
    }

    public function frequency(): ReportFrequency
    {
        return $this->frequency;
    }

    public function current(): ReportDateRange
    {
        return $this->current;
    }

    public function previous(): ReportDateRange
    {
        return $this->previous;
    }

    public function label(): string
    {
        return $this->label;
    }

    private static function daily(CarbonImmutable $now): self
    {
        $current = $now->subDay();
        $previous = $now->subDays(2);

        return new self(
            ReportFrequency::Daily,
            ReportDateRange::custom($current, $current),
            ReportDateRange::custom($previous, $previous),
            $current->format('j M Y'),
        );
    }

    private static function weekly(CarbonImmutable $now): self
    {
        $currentStart = $now->startOfWeek(CarbonInterface::MONDAY)->subWeek();
        $currentEnd = $currentStart->endOfWeek(CarbonInterface::SUNDAY);
        $previousStart = $currentStart->subWeek();
        $previousEnd = $previousStart->endOfWeek(CarbonInterface::SUNDAY);

        return new self(
            ReportFrequency::Weekly,
            ReportDateRange::custom($currentStart, $currentEnd),
            ReportDateRange::custom($previousStart, $previousEnd),
            $currentStart->format('j').'–'.$currentEnd->format('j M Y'),
        );
    }

    private static function monthly(CarbonImmutable $now): self
    {
        $currentStart = $now->subMonthNoOverflow()->startOfMonth();
        $currentEnd = $currentStart->endOfMonth();
        $previousStart = $currentStart->subMonthNoOverflow();
        $previousEnd = $previousStart->endOfMonth();

        return new self(
            ReportFrequency::Monthly,
            ReportDateRange::custom($currentStart, $currentEnd),
            ReportDateRange::custom($previousStart, $previousEnd),
            $currentStart->format('F Y'),
        );
    }
}
