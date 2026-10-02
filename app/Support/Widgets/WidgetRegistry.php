<?php

namespace App\Support\Widgets;

use App\Enums\WidgetType;
use App\Models\Activity;
use App\Models\Project;
use App\Services\StatisticsAggregator;
use Illuminate\Support\Carbon;

class WidgetRegistry
{
    private const DAYS = ['required', 'integer', 'in:7,30,90,365'];

    private const TITLE = ['nullable', 'string', 'max:60'];

    // UI-only preference shared by every widget type: render without the card frame.
    private const HIDE_FRAME = ['nullable', 'boolean'];

    public function __construct(private StatisticsAggregator $stats) {}

    /** @return array<string, array<int, string>> */
    public function configRules(WidgetType $type): array
    {
        return match ($type) {
            WidgetType::Kpi => ['metric' => ['required', 'string', 'in:clicks,unique_visitors,active_links,avg_per_link'], 'days' => self::DAYS, 'title' => self::TITLE, 'hide_frame' => self::HIDE_FRAME],
            WidgetType::Timeseries => ['days' => self::DAYS, 'title' => self::TITLE, 'hide_frame' => self::HIDE_FRAME],
            WidgetType::TopList => ['dimension' => ['required', 'string', 'in:links,countries,cities,browsers,os,referrers'], 'limit' => ['required', 'integer', 'min:1', 'max:20'], 'days' => self::DAYS, 'title' => self::TITLE, 'hide_frame' => self::HIDE_FRAME],
            WidgetType::GeoMap => ['days' => self::DAYS, 'title' => self::TITLE, 'hide_frame' => self::HIDE_FRAME],
            WidgetType::Activity => ['limit' => ['required', 'integer', 'min:1', 'max:20'], 'title' => self::TITLE, 'hide_frame' => self::HIDE_FRAME],
            WidgetType::QuickActions => ['title' => self::TITLE, 'hide_frame' => self::HIDE_FRAME],
        };
    }

    /** @return array{minW:int,minH:int,maxH:int,defaultW:int,defaultH:int} */
    public function layoutBounds(WidgetType $type): array
    {
        return match ($type) {
            WidgetType::Kpi => ['minW' => 2, 'minH' => 2, 'maxH' => 4, 'defaultW' => 3, 'defaultH' => 2],
            WidgetType::Timeseries => ['minW' => 4, 'minH' => 4, 'maxH' => 8, 'defaultW' => 8, 'defaultH' => 5],
            WidgetType::TopList => ['minW' => 3, 'minH' => 3, 'maxH' => 10, 'defaultW' => 4, 'defaultH' => 5],
            WidgetType::GeoMap => ['minW' => 4, 'minH' => 4, 'maxH' => 10, 'defaultW' => 8, 'defaultH' => 6],
            WidgetType::Activity => ['minW' => 3, 'minH' => 3, 'maxH' => 10, 'defaultW' => 4, 'defaultH' => 5],
            WidgetType::QuickActions => ['minW' => 3, 'minH' => 2, 'maxH' => 4, 'defaultW' => 6, 'defaultH' => 2],
        };
    }

    /** @return array<string, mixed> */
    public function data(WidgetType $type, string $projectId, array $config): array
    {
        $days = (int) ($config['days'] ?? 30);
        $now = Carbon::now();
        $since = $now->copy()->subDays($days - 1)->startOfDay();
        $until = $now;
        $prevSince = $since->copy()->subDays($days);
        $prevUntil = $since->copy()->subSecond();

        return match ($type) {
            WidgetType::Kpi => $this->kpi($projectId, (string) $config['metric'], $since, $until, $prevSince, $prevUntil),
            WidgetType::Timeseries => ['series' => $this->stats->clicksByDay($projectId, null, $days)],
            WidgetType::TopList => ['rows' => $this->topList($projectId, (string) $config['dimension'], (int) $config['limit'], $since, $until)],
            WidgetType::GeoMap => ['data' => $this->stats->breakdownByCountryCode($projectId, null, $since, $until)->values()],
            WidgetType::Activity => ['items' => Activity::query()->forProject(Project::findOrFail($projectId))->with('causer')->latest('id')->limit((int) $config['limit'])->get()->map(fn (Activity $a) => $a->toFeedArray())->values()],
            WidgetType::QuickActions => [],
        };
    }

    private function kpi(string $projectId, string $metric, Carbon $since, Carbon $until, Carbon $prevSince, Carbon $prevUntil): array
    {
        [$cur, $prev] = match ($metric) {
            'clicks' => [$this->stats->totalClicks($projectId, null, $since, $until), $this->stats->totalClicks($projectId, null, $prevSince, $prevUntil)],
            'unique_visitors' => [$this->stats->uniqueClicks($projectId, null, $since, $until), $this->stats->uniqueClicks($projectId, null, $prevSince, $prevUntil)],
            'active_links' => [Project::findOrFail($projectId)->urls()->count(), Project::findOrFail($projectId)->urls()->where('created_at', '<', $since)->count()],
            'avg_per_link' => $this->avgPerLink($projectId, $since, $until, $prevSince, $prevUntil),
            default => [0, 0],
        };

        return ['value' => $cur, 'deltaPct' => $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : null];
    }

    private function avgPerLink(string $projectId, Carbon $since, Carbon $until, Carbon $prevSince, Carbon $prevUntil): array
    {
        $project = Project::findOrFail($projectId);
        $linksNow = $project->urls()->count();
        $linksPrev = $project->urls()->where('created_at', '<=', $prevUntil)->count();
        $cur = $linksNow > 0 ? (int) round($this->stats->totalClicks($projectId, null, $since, $until) / $linksNow) : 0;
        $prev = $linksPrev > 0 ? (int) round($this->stats->totalClicks($projectId, null, $prevSince, $prevUntil) / $linksPrev) : 0;

        return [$cur, $prev];
    }

    /** @return array<int, array<string, mixed>> */
    private function topList(string $projectId, string $dimension, int $limit, Carbon $since, Carbon $until): array
    {
        if ($dimension === 'links') {
            return $this->stats->topLinks($projectId, $since, $until, $limit)->values()->all();
        }
        if ($dimension === 'countries') {
            return $this->stats->topCountriesWithCode($projectId, null, $since, $until, $limit)->values()->all();
        }
        $column = match ($dimension) {
            'cities' => 'city',
            'browsers' => 'browser',
            'os' => 'os',
            'referrers' => 'domain',
            default => 'country',
        };

        return $this->stats->breakdown($projectId, null, $column, $since, $until, $limit)->values()->all();
    }
}
