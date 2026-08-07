<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Pure scheduling-math helper: resolves a named report period into a
 * concrete [start, end] instant pair relative to a caller-supplied "now".
 *
 * Deliberately has no framework/DB dependencies and never calls now()/
 * CarbonImmutable::now() itself — the reference instant is always passed in
 * so results are fully deterministic and testable.
 */
class PeriodResolver
{
    private const RELATIVE_DAY_PERIODS = [
        'last_7_days' => 7,
        'last_30_days' => 30,
        'last_90_days' => 90,
    ];

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [start, end]
     */
    public static function resolve(string $period, CarbonImmutable $now): array
    {
        if (array_key_exists($period, self::RELATIVE_DAY_PERIODS)) {
            $days = self::RELATIVE_DAY_PERIODS[$period];

            return [$now->subDays($days), $now];
        }

        if ($period === 'previous_month') {
            $previousMonth = $now->subMonth();

            return [$previousMonth->startOfMonth(), $previousMonth->endOfMonth()];
        }

        throw new InvalidArgumentException("Unknown report period: {$period}");
    }
}
