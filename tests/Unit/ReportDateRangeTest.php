<?php

namespace Tests\Unit;

use App\Reports\ReportDateRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReportDateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-06-18 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_preset_30_spans_thirty_days_ending_today(): void
    {
        $range = ReportDateRange::preset(30);

        $this->assertSame(30, $range->days());
        $this->assertSame('2026-05-20 00:00:00', $range->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-18 23:59:59', $range->end()->format('Y-m-d H:i:s'));
        $this->assertSame('Last 30 days', $range->label());
    }

    public function test_preset_rejects_unknown_window(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReportDateRange::preset(45);
    }

    public function test_custom_range_inclusive_bounds_and_label(): void
    {
        $range = ReportDateRange::custom(
            CarbonImmutable::parse('2026-04-01'),
            CarbonImmutable::parse('2026-04-30'),
        );

        $this->assertSame('2026-04-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-30 23:59:59', $range->end()->format('Y-m-d H:i:s'));
        $this->assertSame(30, $range->days());
        $this->assertSame('1 Apr – 30 Apr 2026', $range->label());
    }

    public function test_custom_rejects_reversed_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReportDateRange::custom(
            CarbonImmutable::parse('2026-04-30'),
            CarbonImmutable::parse('2026-04-01'),
        );
    }

    public function test_custom_rejects_future_end(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReportDateRange::custom(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-07-01'),
        );
    }

    public function test_from_request_falls_back_to_preset_30(): void
    {
        $this->assertSame('Last 30 days', ReportDateRange::fromRequest([])->label());
        $this->assertSame('Last 7 days', ReportDateRange::fromRequest(['range' => 7])->label());
        $this->assertSame(
            '1 Apr – 30 Apr 2026',
            ReportDateRange::fromRequest(['from' => '2026-04-01', 'to' => '2026-04-30'])->label(),
        );
    }
}
