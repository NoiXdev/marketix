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
        'browser',
        'os',
        'device',
        'language',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    /** @param array<string, string> $filters */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly int $days,
        public readonly array $filters = [],
        public readonly bool $hourly = false,
    ) {}

    /** @param array<string, mixed> $filters */
    public static function lastDays(int $days, array $filters = []): self
    {
        $now = CarbonImmutable::now();

        return new self($now->subDays($days - 1)->startOfDay(), $now, $days, self::sanitizeFilters($filters), $days === 1);
    }

    public static function from(self|int $range): self
    {
        return $range instanceof self ? $range : self::lastDays($range);
    }

    public function previous(): self
    {
        return new self($this->from->subDays($this->days), $this->to->subDays($this->days), $this->days, $this->filters, $this->hourly);
    }

    public function daily(): self
    {
        return new self($this->from, $this->to, $this->days, $this->filters, false);
    }

    public function hourly(): bool
    {
        return $this->hourly;
    }

    public function hasFilters(): bool
    {
        return $this->filters !== [];
    }

    public function filter(string $key): ?string
    {
        return $this->filters[$key] ?? null;
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
