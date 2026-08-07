<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Pure scheduling-math helper: computes the next send instant for a
 * scheduled report, strictly after a caller-supplied reference instant.
 *
 * Deliberately has no framework/DB dependencies and never calls now()/
 * CarbonImmutable::now() itself — the reference instant ($after) is always
 * passed in so results are fully deterministic and testable. The instant is
 * interpreted/produced in config('app.timezone').
 */
class NextRunCalculator
{
    private const MAX_DAY_OF_MONTH = 28;

    public static function next(
        string $frequency,
        ?int $weekday,
        ?int $dayOfMonth,
        int $sendHour,
        CarbonImmutable $after,
    ): CarbonImmutable {
        $after = $after->setTimezone(config('app.timezone'));

        return match ($frequency) {
            'daily' => self::nextDaily($sendHour, $after),
            'weekly' => self::nextWeekly($weekday, $sendHour, $after),
            'monthly' => self::nextMonthly($dayOfMonth, $sendHour, $after),
            default => throw new InvalidArgumentException("Unknown report frequency: {$frequency}"),
        };
    }

    private static function nextDaily(int $sendHour, CarbonImmutable $after): CarbonImmutable
    {
        $candidate = $after->setTime($sendHour, 0, 0);

        return $candidate->lessThanOrEqualTo($after) ? $candidate->addDay() : $candidate;
    }

    private static function nextWeekly(?int $weekday, int $sendHour, CarbonImmutable $after): CarbonImmutable
    {
        if ($weekday === null) {
            throw new InvalidArgumentException('Weekly frequency requires a weekday.');
        }

        $candidate = $after->setTime($sendHour, 0, 0);

        while ($candidate->dayOfWeek !== $weekday || $candidate->lessThanOrEqualTo($after)) {
            $candidate = $candidate->addDay();
        }

        return $candidate;
    }

    private static function nextMonthly(?int $dayOfMonth, int $sendHour, CarbonImmutable $after): CarbonImmutable
    {
        if ($dayOfMonth === null) {
            throw new InvalidArgumentException('Monthly frequency requires a day of month.');
        }

        $day = min($dayOfMonth, self::MAX_DAY_OF_MONTH);

        $candidate = $after->startOfMonth()->addDays($day - 1)->setTime($sendHour, 0, 0);

        while ($candidate->lessThanOrEqualTo($after)) {
            $candidate = $candidate->addMonth();
        }

        return $candidate;
    }
}
