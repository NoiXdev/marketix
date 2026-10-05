<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Throwable;

final class PeriodResolver
{
    public const RANGES = ['today', 'yesterday', '7d', '30d', '90d', 'month', 'last_month', 'year', '12m', 'custom'];

    public const COMPARISONS = ['previous', 'year', 'none'];

    public const DEFAULT_RANGE = '30d';

    public const MAX_DAYS = 731;

    private const LEGACY_DAYS = [1 => 'today', 7 => '7d', 30 => '30d', 90 => '90d'];

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $filters
     */
    public static function resolve(array $input, array $filters = [], ?CarbonImmutable $now = null): ResolvedPeriod
    {
        $now ??= CarbonImmutable::now();

        $range = $input['range'] ?? self::LEGACY_DAYS[(int) ($input['days'] ?? 0)] ?? self::DEFAULT_RANGE;
        $range = in_array($range, self::RANGES, true) ? $range : self::DEFAULT_RANGE;

        $bounds = $range === 'custom'
            ? self::custom($input['from'] ?? null, $input['to'] ?? null, $now)
            : self::preset($range, $now);

        if ($bounds === null) {
            $range = self::DEFAULT_RANGE;
            $bounds = self::preset($range, $now);
        }

        $compare = in_array($input['compare'] ?? null, self::COMPARISONS, true) ? $input['compare'] : 'previous';
        $interval = is_string($input['interval'] ?? null) ? $input['interval'] : null;

        return new ResolvedPeriod(
            AnalyticsQuery::between($bounds[0], $bounds[1], $filters, $interval),
            $range,
            $compare,
        );
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function preset(string $range, CarbonImmutable $now): array
    {
        return match ($range) {
            'today' => [$now->startOfDay(), $now],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now],
            '90d' => [$now->subDays(89)->startOfDay(), $now],
            'month' => [$now->startOfMonth(), $now],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'year' => [$now->startOfYear(), $now],
            '12m' => [$now->subMonthsNoOverflow(11)->startOfMonth(), $now],
            default => [$now->subDays(29)->startOfDay(), $now],
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable}|null */
    private static function custom(mixed $from, mixed $to, CarbonImmutable $now): ?array
    {
        if (! is_string($from) || ! is_string($to)) {
            return null;
        }

        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);
        } catch (Throwable) {
            return null;
        }

        if ($start === false || $end === false || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to) {
            return null;
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->gt($now)) {
            return null;
        }

        $end = $end->endOfDay()->min($now);
        $start = $start->max($end->startOfDay()->subDays(self::MAX_DAYS - 1));

        return [$start, $end];
    }
}
