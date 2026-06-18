<?php

namespace App\Reports;

use App\Models\Project;
use App\Models\Statistic;
use App\Models\Url;
use App\Services\StatisticsAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReportDataService
{
    public function __construct(private readonly StatisticsAggregator $stats) {}

    public function forProject(Project $project, ReportDateRange $range): ReportData
    {
        return new ReportData(
            scope: 'project',
            title: "Statistics report — {$project->name}",
            subtitle: $project->name,
            rangeLabel: $range->label(),
            generatedAt: CarbonImmutable::now()->format('j M Y, H:i'),
            totalClicks: $this->stats->totalClicks($project->id, null, $range->start(), $range->end()),
            uniqueClicks: $this->stats->uniqueClicks($project->id, null, $range->start(), $range->end()),
            timeSeries: $this->stats->clicksByDayBetween($project->id, null, $range->start(), $range->end()),
            breakdowns: $this->breakdowns($project->id, null, $range),
            topLinks: $this->topLinks($project->id, $range),
        );
    }

    public function forUrl(Url $url, ReportDateRange $range): ReportData
    {
        $recent = $this->stats
            ->recentClicks($url->project_id, $url->id, $range->start(), $range->end())
            ->map(fn ($r) => [
                'country' => $r->country,
                'city' => $r->city,
                'browser' => $r->browser,
                'os' => $r->os,
                'domain' => $r->domain,
                'created_at' => (string) $r->created_at,
            ])->all();

        return new ReportData(
            scope: 'link',
            title: "Link report — /{$url->slug}",
            subtitle: $url->url,
            rangeLabel: $range->label(),
            generatedAt: CarbonImmutable::now()->format('j M Y, H:i'),
            totalClicks: $this->stats->totalClicks($url->project_id, $url->id, $range->start(), $range->end()),
            uniqueClicks: $this->stats->uniqueClicks($url->project_id, $url->id, $range->start(), $range->end()),
            timeSeries: $this->stats->clicksByDayBetween($url->project_id, $url->id, $range->start(), $range->end()),
            breakdowns: $this->breakdowns($url->project_id, $url->id, $range),
            recentClicks: $recent,
        );
    }

    /**
     * @return array<string,list<array{label:string,count:int}>>
     */
    private function breakdowns(int $projectId, ?int $urlId, ReportDateRange $range): array
    {
        $out = [];
        foreach (['country', 'city', 'browser', 'os', 'domain'] as $column) {
            $out[$column] = $this->stats
                ->breakdown($projectId, $urlId, $column, $range->start(), $range->end())
                ->map(fn ($row) => ['label' => (string) $row->{$column}, 'count' => (int) $row->count])
                ->all();
        }

        return $out;
    }

    /**
     * @return list<array{slug:string,domain:string,clicks:int}>
     */
    private function topLinks(int $projectId, ReportDateRange $range): array
    {
        return Statistic::where('statistics.project_id', $projectId)
            ->whereBetween('statistics.created_at', [$range->start(), $range->end()])
            ->join('urls', 'statistics.url_id', '=', 'urls.id')
            ->join('domains', 'urls.domain_id', '=', 'domains.id')
            ->select('urls.slug', 'domains.name as domain', DB::raw('COUNT(*) as clicks'))
            ->groupBy('urls.slug', 'domains.name')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['slug' => $r->slug, 'domain' => $r->domain, 'clicks' => (int) $r->clicks])
            ->all();
    }
}
