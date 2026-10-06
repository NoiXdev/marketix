<?php

namespace App\Services;

use App\Models\Event;
use App\Models\PageView;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RealtimeAggregator
{
    public const WINDOW_MINUTES = 30;

    public function __construct(private AnalyticsAggregator $analytics = new AnalyticsAggregator) {}

    private function pageViews(string $siteId, CarbonImmutable $since): Builder
    {
        return PageView::query()->where('site_id', $siteId)->where('is_bot', false)->where('created_at', '>=', $since);
    }

    private function minuteExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:%M', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')";
    }

    /** @return array<string, mixed> */
    public function snapshot(string $siteId): array
    {
        $now = CarbonImmutable::now();
        $since = $now->subMinutes(self::WINDOW_MINUTES - 1)->startOfMinute();

        $totals = $this->pageViews($siteId, $since)
            ->select(DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors'))
            ->first();

        return [
            'active_now' => $this->analytics->liveVisitors($siteId),
            'visitors' => (int) ($totals->visitors ?? 0),
            'page_views' => (int) ($totals->views ?? 0),
            'minutes' => $this->minutes($siteId, $since, $now),
            'pages' => $this->breakdown($siteId, $since, 'path'),
            'countries' => $this->breakdown($siteId, $since, 'country_code'),
            'devices' => $this->breakdown($siteId, $since, 'device'),
            'channels' => $this->channels($siteId, $since),
            'feed' => $this->feed($siteId, $since, $now),
        ];
    }

    /** @return list<array{minutes_ago: int, views: int, visitors: int}> */
    private function minutes(string $siteId, CarbonImmutable $since, CarbonImmutable $now): array
    {
        $rows = $this->pageViews($siteId, $since)
            ->select(DB::raw($this->minuteExpression().' as minute'), DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT visitor_hash) as visitors'))
            ->groupBy('minute')
            ->get()
            ->keyBy('minute');

        $current = $now->startOfMinute();
        $out = [];
        for ($ago = self::WINDOW_MINUTES - 1; $ago >= 0; $ago--) {
            $row = $rows->get($current->subMinutes($ago)->format('Y-m-d H:i'));
            $out[] = ['minutes_ago' => $ago, 'views' => (int) ($row->views ?? 0), 'visitors' => (int) ($row->visitors ?? 0)];
        }

        return $out;
    }

    /** @return list<array{value: string, count: int}> */
    private function breakdown(string $siteId, CarbonImmutable $since, string $column): array
    {
        return $this->pageViews($siteId, $since)
            ->whereNotNull($column)->where($column, '!=', '')
            ->select("{$column} as value", DB::raw('COUNT(DISTINCT visitor_hash) as count'))
            ->groupBy($column)
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['value' => (string) $row->value, 'count' => (int) $row->count])
            ->all();
    }

    /** @return list<array{value: string, count: int}> */
    private function channels(string $siteId, CarbonImmutable $since): array
    {
        [$sql, $bindings] = $this->analytics->channelExpression($siteId);

        return Visit::query()
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->where('last_activity_at', '>=', $since)
            ->selectRaw("{$sql} as value", $bindings)
            ->selectRaw('COUNT(DISTINCT visitor_hash) as count')
            ->groupBy('value')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['value' => (string) $row->value, 'count' => (int) $row->count])
            ->all();
    }

    /** @return list<array{type: string, label: string, path: string, country_code: ?string, device: ?string, seconds_ago: int}> */
    private function feed(string $siteId, CarbonImmutable $since, CarbonImmutable $now, int $limit = 15): array
    {
        $views = $this->pageViews($siteId, $since)
            ->latest('created_at')
            ->limit($limit)
            ->get(['path', 'country_code', 'device', 'created_at'])
            ->map(fn (PageView $view) => [
                'type' => 'pageview',
                'label' => $view->path,
                'path' => $view->path,
                'country_code' => $view->country_code,
                'device' => $view->device,
                'at' => $view->created_at,
            ]);

        $events = Event::query()
            ->with('visit:id,country_code,device')
            ->where('site_id', $siteId)
            ->where('is_bot', false)
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            ->limit($limit)
            ->get(['visit_id', 'name', 'path', 'created_at'])
            ->map(fn (Event $event) => [
                'type' => 'event',
                'label' => $event->name,
                'path' => $event->path,
                'country_code' => $event->visit?->country_code,
                'device' => $event->visit?->device,
                'at' => $event->created_at,
            ]);

        return $views->concat($events)
            ->sortByDesc(fn (array $item) => $item['at']->getTimestamp())
            ->take($limit)
            ->map(function (array $item) use ($now) {
                $item['seconds_ago'] = max(0, (int) $item['at']->diffInSeconds($now));
                unset($item['at']);

                return $item;
            })
            ->values()
            ->all();
    }
}
