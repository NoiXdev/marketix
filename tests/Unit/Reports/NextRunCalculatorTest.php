<?php

namespace Tests\Unit\Reports;

use App\Reports\NextRunCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class NextRunCalculatorTest extends TestCase
{
    public function test_daily_returns_next_day_at_send_hour_when_after_is_past_send_hour(): void
    {
        $after = CarbonImmutable::parse('2026-03-15 10:00:00', config('app.timezone'));

        $next = NextRunCalculator::next('daily', null, null, 8, $after);

        $this->assertSame('2026-03-16 08:00:00', $next->toDateTimeString());
    }

    public function test_daily_returns_same_day_at_send_hour_when_after_is_before_send_hour(): void
    {
        $after = CarbonImmutable::parse('2026-03-15 05:00:00', config('app.timezone'));

        $next = NextRunCalculator::next('daily', null, null, 8, $after);

        $this->assertSame('2026-03-15 08:00:00', $next->toDateTimeString());
    }

    public function test_daily_strictly_after_skips_to_next_day_when_after_equals_send_instant(): void
    {
        $after = CarbonImmutable::parse('2026-03-16 08:00:00', config('app.timezone'));

        $next = NextRunCalculator::next('daily', null, null, 8, $after);

        $this->assertSame('2026-03-17 08:00:00', $next->toDateTimeString());
    }

    public function test_weekly_from_mid_week_returns_next_matching_weekday(): void
    {
        // 2026-03-18 is a Wednesday; weekday=1 is Monday (Carbon: 0=Sun..6=Sat).
        $after = CarbonImmutable::parse('2026-03-18 10:00:00', config('app.timezone'));

        $next = NextRunCalculator::next('weekly', 1, null, 8, $after);

        // Nearest Monday (2026-03-23), not the already-passed 2026-03-16.
        $this->assertSame('2026-03-23 08:00:00', $next->toDateTimeString());
    }

    public function test_weekly_strictly_after_skips_to_next_week_when_after_equals_send_instant(): void
    {
        // 2026-03-23 is a Monday.
        $after = CarbonImmutable::parse('2026-03-23 08:00:00', config('app.timezone'));

        $next = NextRunCalculator::next('weekly', 1, null, 8, $after);

        $this->assertSame('2026-03-30 08:00:00', $next->toDateTimeString());
    }

    public function test_monthly_day_of_month_31_is_clamped_to_28(): void
    {
        $after = CarbonImmutable::parse('2026-03-15 10:00:00', config('app.timezone'));

        $next = NextRunCalculator::next('monthly', null, 31, 8, $after);

        $this->assertSame('2026-03-28 08:00:00', $next->toDateTimeString());
    }

    public function test_monthly_rolls_over_to_next_month_when_clamped_day_has_passed(): void
    {
        $after = CarbonImmutable::parse('2026-03-30 10:00:00', config('app.timezone'));

        $next = NextRunCalculator::next('monthly', null, 31, 8, $after);

        $this->assertSame('2026-04-28 08:00:00', $next->toDateTimeString());
    }

    public function test_monthly_strictly_after_skips_to_next_month_when_after_equals_send_instant(): void
    {
        $after = CarbonImmutable::parse('2026-03-28 08:00:00', config('app.timezone'));

        $next = NextRunCalculator::next('monthly', null, 31, 8, $after);

        $this->assertSame('2026-04-28 08:00:00', $next->toDateTimeString());
    }

    public function test_unknown_frequency_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NextRunCalculator::next('bogus', null, null, 8, CarbonImmutable::parse('2026-03-15 10:00:00', config('app.timezone')));
    }
}
