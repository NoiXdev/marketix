<?php

namespace Tests\Unit;

use App\Enums\ReportFrequency;
use App\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReportPeriodTest extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        // A Friday, 08:00.
        $this->now = CarbonImmutable::parse('2026-06-19 08:00:00');
    }

    public function test_daily_covers_yesterday_and_previous_is_the_day_before(): void
    {
        $period = ReportPeriod::for(ReportFrequency::Daily, $this->now);

        $this->assertSame('2026-06-18 00:00:00', $period->current()->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-18 23:59:59', $period->current()->end()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-17 00:00:00', $period->previous()->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-17 23:59:59', $period->previous()->end()->format('Y-m-d H:i:s'));
        $this->assertSame('18 Jun 2026', $period->label());
    }

    public function test_weekly_covers_previous_monday_to_sunday(): void
    {
        $period = ReportPeriod::for(ReportFrequency::Weekly, $this->now);

        // Week before the 19th: Mon 8 Jun – Sun 14 Jun.
        $this->assertSame('2026-06-08 00:00:00', $period->current()->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-14 23:59:59', $period->current()->end()->format('Y-m-d H:i:s'));
        // Previous week: Mon 1 Jun – Sun 7 Jun.
        $this->assertSame('2026-06-01 00:00:00', $period->previous()->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-07 23:59:59', $period->previous()->end()->format('Y-m-d H:i:s'));
        $this->assertSame('8–14 Jun 2026', $period->label());
    }

    public function test_monthly_covers_previous_calendar_month(): void
    {
        $period = ReportPeriod::for(ReportFrequency::Monthly, $this->now);

        $this->assertSame('2026-05-01 00:00:00', $period->current()->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-31 23:59:59', $period->current()->end()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-01 00:00:00', $period->previous()->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-30 23:59:59', $period->previous()->end()->format('Y-m-d H:i:s'));
        $this->assertSame('May 2026', $period->label());
    }

    public function test_off_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReportPeriod::for(ReportFrequency::Off, $this->now);
    }

    public function test_weekly_on_a_sunday_anchor_still_targets_the_previous_full_week(): void
    {
        // 2026-06-21 is a Sunday.
        $period = ReportPeriod::for(ReportFrequency::Weekly, CarbonImmutable::parse('2026-06-21 08:00:00'));

        // The most-recently-completed Mon–Sun week before the 21st is Jun 8–14.
        $this->assertSame('2026-06-08 00:00:00', $period->current()->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-14 23:59:59', $period->current()->end()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-01 00:00:00', $period->previous()->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-07 23:59:59', $period->previous()->end()->format('Y-m-d H:i:s'));
    }
}
