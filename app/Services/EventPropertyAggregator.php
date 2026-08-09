<?php

namespace App\Services;

use App\Models\Event;

class EventPropertyAggregator
{
    public const TOP_VALUES = 10;

    /**
     * Aggregate the scalar top-level props of one event over the range.
     *
     * @return array{
     *   total: int,
     *   keys: list<array{
     *     key: string,
     *     values: list<array{value: string, count: int}>,
     *     numeric: array{count: int, sum: float, avg: float}|null
     *   }>
     * }
     */
    public function forEvent(string $siteId, string $name, int $days): array
    {
        $base = Event::query()
            ->where('site_id', $siteId)
            ->where('name', $name)
            ->where('is_bot', false)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay());

        $total = (clone $base)->count();

        /** @var array<string, array<string, int>> $counts */
        $counts = [];
        /** @var array<string, array{count: int, sum: float}> $numeric */
        $numeric = [];

        (clone $base)->select(['id', 'props'])->chunkById(1000, function ($rows) use (&$counts, &$numeric) {
            foreach ($rows as $row) {
                $props = $row->props;
                if (! is_array($props)) {
                    continue;
                }

                foreach ($props as $key => $value) {
                    if (is_array($value) || $value === null) {
                        continue; // skip nested objects/arrays and nulls
                    }

                    $key = (string) $key;
                    $label = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

                    $counts[$key][$label] = ($counts[$key][$label] ?? 0) + 1;

                    if (is_int($value) || is_float($value)) {
                        $numeric[$key]['count'] = ($numeric[$key]['count'] ?? 0) + 1;
                        $numeric[$key]['sum'] = ($numeric[$key]['sum'] ?? 0) + $value;
                    }
                }
            }
        });

        ksort($counts);

        $keys = [];
        foreach ($counts as $key => $valueCounts) {
            arsort($valueCounts);
            $values = [];
            foreach (array_slice($valueCounts, 0, self::TOP_VALUES, true) as $value => $count) {
                $values[] = ['value' => (string) $value, 'count' => $count];
            }

            $num = null;
            if (isset($numeric[$key]) && $numeric[$key]['count'] > 0) {
                $n = $numeric[$key]['count'];
                $sum = (float) $numeric[$key]['sum'];
                $num = ['count' => $n, 'sum' => round($sum, 2), 'avg' => round($sum / $n, 2)];
            }

            $keys[] = ['key' => $key, 'values' => $values, 'numeric' => $num];
        }

        return ['total' => $total, 'keys' => $keys];
    }
}
