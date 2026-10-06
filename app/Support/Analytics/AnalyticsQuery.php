<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;

final class AnalyticsQuery
{
    public const FILTERS = [
        'path',
        'entry_path',
        'exit_path',
        'referer_domain',
        'channel',
        'country_code',
        'region',
        'city',
        'browser',
        'os',
        'device',
        'language',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    public const INTERVALS = ['hour', 'day', 'week', 'month'];

    /** @param array<string, string> $filters */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly array $filters = [],
        public readonly string $interval = 'day',
    ) {}

    /** @param array<string, mixed> $filters */
    public static function lastDays(int $days, array $filters = []): self
    {
        $now = CarbonImmutable::now();

        return new self($now->subDays($days - 1)->startOfDay(), $now, self::sanitizeFilters($filters), $days === 1 ? 'hour' : 'day');
    }

    /** @param array<string, mixed> $filters */
    public static function between(CarbonImmutable $from, CarbonImmutable $to, array $filters = [], ?string $interval = null): self
    {
        $days = self::spanInDays($from, $to);
        $interval = in_array($interval, self::allowedIntervals($days), true) ? $interval : self::defaultInterval($days);

        return new self($from, $to, self::sanitizeFilters($filters), $interval);
    }

    public static function from(self|int $range): self
    {
        return $range instanceof self ? $range : self::lastDays($range);
    }

    public function days(): int
    {
        return self::spanInDays($this->from, $this->to);
    }

    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->subDays($days), $this->to->subDays($days), $this->filters, $this->interval);
    }

    public function previousYear(): self
    {
        return new self($this->from->subYearNoOverflow(), $this->to->subYearNoOverflow(), $this->filters, $this->interval);
    }

    public function daily(): self
    {
        return new self($this->from, $this->to, $this->filters, 'day');
    }

    public function hourly(): bool
    {
        return $this->interval === 'hour';
    }

    public function hasFilters(): bool
    {
        return $this->filters !== [];
    }

    public function filter(string $key): ?string
    {
        return $this->filters[$key] ?? null;
    }

    /** @return list<string> */
    public static function allowedIntervals(int $days): array
    {
        return match (true) {
            $days <= 7 => ['hour', 'day'],
            $days <= 92 => ['day', 'week'],
            $days <= 366 => ['day', 'week', 'month'],
            default => ['week', 'month'],
        };
    }

    public static function defaultInterval(int $days): string
    {
        return match (true) {
            $days <= 2 => 'hour',
            $days <= 92 => 'day',
            $days <= 366 => 'week',
            default => 'month',
        };
    }

    private static function spanInDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public static function sanitizeFilters(array $filters): array
    {
        $clean = [];
        foreach (self::FILTERS as $key) {
            $value = $filters[$key] ?? null;
            if (is_string($value) && $value !== '' && strlen($value) <= 2048) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
