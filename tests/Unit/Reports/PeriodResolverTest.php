<?php

namespace Tests\Unit\Reports;

use App\Reports\PeriodResolver;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class PeriodResolverTest extends TestCase
{
    public function test_last_7_days_spans_seven_days_ending_now(): void
    {
        $now = CarbonImmutable::parse('2026-03-15 10:00', config('app.timezone'));

        [$start, $end] = PeriodResolver::resolve('last_7_days', $now);

        $this->assertSame('2026-03-08 10:00:00', $start->toDateTimeString());
        $this->assertSame('2026-03-15 10:00:00', $end->toDateTimeString());
    }

    public function test_last_30_days_spans_thirty_days_ending_now(): void
    {
        $now = CarbonImmutable::parse('2026-03-15 10:00', config('app.timezone'));

        [$start, $end] = PeriodResolver::resolve('last_30_days', $now);

        $this->assertSame('2026-02-13 10:00:00', $start->toDateTimeString());
        $this->assertSame('2026-03-15 10:00:00', $end->toDateTimeString());
    }

    public function test_last_90_days_spans_ninety_days_ending_now(): void
    {
        $now = CarbonImmutable::parse('2026-03-15 10:00', config('app.timezone'));

        [$start, $end] = PeriodResolver::resolve('last_90_days', $now);

        $this->assertSame('2025-12-15 10:00:00', $start->toDateTimeString());
        $this->assertSame('2026-03-15 10:00:00', $end->toDateTimeString());
    }

    public function test_previous_month_spans_full_calendar_month(): void
    {
        $now = CarbonImmutable::parse('2026-03-15 10:00', config('app.timezone'));

        [$start, $end] = PeriodResolver::resolve('previous_month', $now);

        $this->assertSame('2026-02-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-02-28 23:59:59', $end->toDateTimeString());
    }

    public function test_previous_month_from_january_rolls_back_to_prior_december(): void
    {
        $now = CarbonImmutable::parse('2026-01-15 10:00', config('app.timezone'));

        [$start, $end] = PeriodResolver::resolve('previous_month', $now);

        $this->assertSame('2025-12-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2025-12-31 23:59:59', $end->toDateTimeString());
    }

    public function test_previous_month_from_day_31_does_not_overflow_into_current_month(): void
    {
        // Regression guard: naive $now->subMonth() from a day-31 date uses
        // Carbon's overflowing arithmetic and can land back in the *same*
        // month (2026-03-31 minus 1 month -> 2026-03-03), which would make
        // this incorrectly resolve to March instead of February.
        $now = CarbonImmutable::parse('2026-03-31 10:00', config('app.timezone'));

        [$start, $end] = PeriodResolver::resolve('previous_month', $now);

        $this->assertSame('2026-02-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-02-28 23:59:59', $end->toDateTimeString());
    }

    public function test_previous_month_from_day_31_in_a_31_day_month_resolves_to_prior_30_day_month(): void
    {
        $now = CarbonImmutable::parse('2026-05-31 10:00', config('app.timezone'));

        [$start, $end] = PeriodResolver::resolve('previous_month', $now);

        $this->assertSame('2026-04-01 00:00:00', $start->toDateTimeString());
        $this->assertSame('2026-04-30 23:59:59', $end->toDateTimeString());
    }

    public function test_unknown_period_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PeriodResolver::resolve('bogus_period', CarbonImmutable::parse('2026-03-15 10:00', config('app.timezone')));
    }
}
