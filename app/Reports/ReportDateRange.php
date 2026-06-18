<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ReportDateRange
{
    private const PRESETS = [7, 30, 90];

    private const MAX_SPAN_DAYS = 366;

    private function __construct(
        private readonly CarbonImmutable $start,
        private readonly CarbonImmutable $end,
        private readonly ?int $presetDays,
    ) {}

    public static function preset(int $days): self
    {
        if (! in_array($days, self::PRESETS, true)) {
            throw new InvalidArgumentException("Unsupported preset window: {$days}");
        }

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();

        return new self($start, $end, $days);
    }

    public static function custom(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $start = $from->startOfDay();
        $end = $to->endOfDay();

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('Range start must not be after range end.');
        }
        if ($end->greaterThan(CarbonImmutable::now()->endOfDay())) {
            throw new InvalidArgumentException('Range end must not be in the future.');
        }
        if ($start->diffInDays($end) + 1 > self::MAX_SPAN_DAYS) {
            throw new InvalidArgumentException('Range exceeds the maximum supported span.');
        }

        return new self($start, $end, null);
    }

    public static function fromRequest(array $input): self
    {
        if (isset($input['from'], $input['to']) && $input['from'] !== null && $input['to'] !== null) {
            return self::custom(
                CarbonImmutable::parse($input['from']),
                CarbonImmutable::parse($input['to']),
            );
        }

        $range = isset($input['range']) ? (int) $input['range'] : 30;

        return self::preset(in_array($range, self::PRESETS, true) ? $range : 30);
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    public function end(): CarbonImmutable
    {
        return $this->end;
    }

    public function days(): int
    {
        return $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;
    }

    public function label(): string
    {
        if ($this->presetDays !== null) {
            return "Last {$this->presetDays} days";
        }

        return $this->start->format('j M Y') === $this->end->format('j M Y')
            ? $this->start->format('j M Y')
            : $this->start->format('j M').' – '.$this->end->format('j M Y');
    }
}
