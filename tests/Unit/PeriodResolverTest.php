<?php

namespace Tests\Unit;

use App\Support\Analytics\AnalyticsQuery;
use App\Support\Analytics\PeriodResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PeriodResolverTest extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-10-07 15:30:00');
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function presets(): array
    {
        return [
            'today' => ['today', '2026-10-07 00:00:00', '2026-10-07 15:30:00', 'hour'],
            'yesterday' => ['yesterday', '2026-10-06 00:00:00', '2026-10-06 23:59:59', 'hour'],
            '7d' => ['7d', '2026-10-01 00:00:00', '2026-10-07 15:30:00', 'day'],
            '30d' => ['30d', '2026-09-08 00:00:00', '2026-10-07 15:30:00', 'day'],
            '90d' => ['90d', '2026-07-10 00:00:00', '2026-10-07 15:30:00', 'day'],
            'month' => ['month', '2026-10-01 00:00:00', '2026-10-07 15:30:00', 'day'],
            'last_month' => ['last_month', '2026-09-01 00:00:00', '2026-09-30 23:59:59', 'day'],
            'year' => ['year', '2026-01-01 00:00:00', '2026-10-07 15:30:00', 'week'],
            '12m' => ['12m', '2025-11-01 00:00:00', '2026-10-07 15:30:00', 'week'],
        ];
    }

    #[DataProvider('presets')]
    public function test_presets_resolve_to_expected_windows(string $range, string $from, string $to, string $interval): void
    {
        $period = PeriodResolver::resolve(['range' => $range], [], $this->now);

        $this->assertSame($range, $period->range);
        $this->assertSame($from, $period->query->from->format('Y-m-d H:i:s'));
        $this->assertSame($to, $period->query->to->format('Y-m-d H:i:s'));
        $this->assertSame($interval, $period->query->interval);
    }

    public function test_legacy_days_parameter_maps_to_presets(): void
    {
        $this->assertSame('today', PeriodResolver::resolve(['days' => '1'], [], $this->now)->range);
        $this->assertSame('90d', PeriodResolver::resolve(['days' => 90], [], $this->now)->range);
        $this->assertSame('30d', PeriodResolver::resolve(['days' => 12], [], $this->now)->range);
        $this->assertSame('30d', PeriodResolver::resolve(['range' => 'forever'], [], $this->now)->range);
    }

    public function test_custom_range_is_parsed_swapped_and_clamped_to_now(): void
    {
        $period = PeriodResolver::resolve(['range' => 'custom', 'from' => '2026-10-09', 'to' => '2026-09-20'], [], $this->now);

        $this->assertSame('custom', $period->range);
        $this->assertSame('2026-09-20 00:00:00', $period->query->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-07 15:30:00', $period->query->to->format('Y-m-d H:i:s'));
    }

    public function test_custom_range_is_limited_to_the_maximum_span(): void
    {
        $period = PeriodResolver::resolve(['range' => 'custom', 'from' => '2020-01-01', 'to' => '2026-06-30'], [], $this->now);

        $this->assertSame(PeriodResolver::MAX_DAYS, $period->query->days());
        $this->assertSame('2026-06-30', $period->query->to->toDateString());
    }

    public function test_invalid_custom_ranges_fall_back_to_the_default(): void
    {
        foreach ([['from' => '2026-02-30', 'to' => '2026-03-01'], ['from' => 'yesterday', 'to' => '2026-10-01'], ['from' => '2027-01-01', 'to' => '2027-01-05'], []] as $input) {
            $period = PeriodResolver::resolve(['range' => 'custom'] + $input, [], $this->now);
            $this->assertSame('30d', $period->range, json_encode($input));
        }
    }

    public function test_interval_must_be_allowed_for_the_span(): void
    {
        $this->assertSame('week', PeriodResolver::resolve(['range' => '30d', 'interval' => 'week'], [], $this->now)->query->interval);
        $this->assertSame('day', PeriodResolver::resolve(['range' => '30d', 'interval' => 'hour'], [], $this->now)->query->interval);
        $this->assertSame('month', PeriodResolver::resolve(['range' => '12m', 'interval' => 'month'], [], $this->now)->query->interval);
        $this->assertSame(['hour', 'day'], AnalyticsQuery::allowedIntervals(7));
        $this->assertSame(['week', 'month'], AnalyticsQuery::allowedIntervals(400));
    }

    public function test_comparison_periods(): void
    {
        $previous = PeriodResolver::resolve(['range' => '7d'], [], $this->now);
        $this->assertSame('2026-09-24', $previous->comparison()->from->toDateString());
        $this->assertSame('2026-09-30 15:30:00', $previous->comparison()->to->format('Y-m-d H:i:s'));

        $year = PeriodResolver::resolve(['range' => 'month', 'compare' => 'year'], [], $this->now);
        $this->assertSame('2025-10-01', $year->comparison()->from->toDateString());
        $this->assertSame('2025-10-07', $year->comparison()->to->toDateString());

        $this->assertNull(PeriodResolver::resolve(['compare' => 'none'], [], $this->now)->comparison());
        $this->assertSame('previous', PeriodResolver::resolve(['compare' => 'bogus'], [], $this->now)->compare);
    }

    public function test_filters_are_sanitized_into_the_query(): void
    {
        $period = PeriodResolver::resolve([], ['path' => '/a', 'evil' => 'x'], $this->now);

        $this->assertSame(['path' => '/a'], $period->query->filters);
    }
}
