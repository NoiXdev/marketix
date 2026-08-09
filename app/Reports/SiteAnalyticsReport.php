<?php

namespace App\Reports;

use App\Models\Project;
use App\Reports\Csv\WritesCsv;
use App\Services\AnalyticsAggregator;
use App\Services\GoalAggregator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Site analytics summary mirroring AnalyticsController@show: visits, page
 * views, goal conversions and top events for the window. Subject must be a
 * Site id belonging to the project.
 *
 * Unlike ProjectSummaryReport/LinkReport, this type does not reuse the
 * click-shaped ReportData struct — AnalyticsAggregator/GoalAggregator model
 * a different domain (site visits/page views, not URL clicks), so
 * viewData() returns its own array shape built directly from them.
 *
 * AnalyticsAggregator/GoalAggregator only support a trailing "last N days
 * ending now()" window (no absolute [start, end] range), unlike
 * ReportDataService/StatisticsAggregator. ReportDateRange::days() is
 * therefore used as that day count — for the "previous_month" period this
 * is an approximation (a trailing N-day window ending today, not the exact
 * historical month), consistent with how the Analytics page itself always
 * windows relative to now().
 */
final class SiteAnalyticsReport implements ReportType
{
    use WritesCsv;

    public function __construct(
        private readonly AnalyticsAggregator $analytics,
        private readonly GoalAggregator $goals,
    ) {}

    public function key(): string
    {
        return 'site_analytics';
    }

    public function validateSubject(Project $project, ?string $subjectId): bool
    {
        return $subjectId !== null && $project->sites()->whereKey($subjectId)->exists();
    }

    public function subjectLabel(Project $project, ?string $subjectId): ?string
    {
        if ($subjectId === null) {
            return null;
        }

        return $project->sites()->whereKey($subjectId)->value('name');
    }

    public function title(Project $project, ?string $subjectId): string
    {
        if ($subjectId === null) {
            throw new InvalidArgumentException('SiteAnalyticsReport requires a subject id.');
        }

        $name = $project->sites()->whereKey($subjectId)->value('name');

        return "Site analytics report — {$name}";
    }

    public function viewData(Project $project, ?string $subjectId, ReportDateRange $range): array
    {
        if ($subjectId === null) {
            throw new InvalidArgumentException('SiteAnalyticsReport requires a subject id.');
        }

        $site = $project->sites()->find($subjectId);

        if ($site === null) {
            throw new InvalidArgumentException('SiteAnalyticsReport subject id does not resolve to a Site in this project.');
        }

        $days = $range->days();

        $goals = $site->goals()->get()->map(function ($goal) use ($days) {
            $conversions = $this->goals->conversions($goal, $days);

            return [
                'name' => $goal->name,
                'conversions' => $conversions['conversions'],
                'visitors' => $conversions['visitors'],
                'rate' => $conversions['rate'],
            ];
        })->all();

        $topEvents = $this->goals->topEvents($site->id, $days, 8)
            ->map(fn ($row) => [
                'name' => $row->name,
                'count' => (int) $row->count,
                'visitors' => (int) $row->visitors,
            ])->all();

        return [
            'title' => "Site analytics report — {$site->name}",
            'subtitle' => $site->name,
            'rangeLabel' => $range->label(),
            'generatedAt' => CarbonImmutable::now()->format('j M Y, H:i'),
            'visits' => $this->analytics->totalSessions($site->id, $days),
            'page_views' => $this->analytics->totalPageViews($site->id, $days),
            'goals' => $goals,
            'top_events' => $topEvents,
            'series' => $this->analytics->pageViewsByDay($site->id, $days),
        ];
    }

    public function pdfView(): string
    {
        return 'reports.site';
    }

    public function emailView(): string
    {
        return 'reports.email.site';
    }

    public function csv(array $viewData): string
    {
        $rows = [
            [$viewData['title'] ?? '', $viewData['rangeLabel'] ?? ''],
            ['Visits', $viewData['visits'] ?? 0],
            ['Page views', $viewData['page_views'] ?? 0],
            [],
            ['Date', 'Views', 'Visitors'],
        ];

        foreach ($viewData['series'] ?? [] as $point) {
            $rows[] = [$point['date'], $point['views'], $point['visitors']];
        }

        $goals = $viewData['goals'] ?? [];
        if ($goals !== []) {
            $rows[] = [];
            $rows[] = ['Goals', ''];
            $rows[] = ['Name', 'Conversions', 'Visitors', 'Rate (%)'];
            foreach ($goals as $goal) {
                $rows[] = [$goal['name'], $goal['conversions'], $goal['visitors'], $goal['rate']];
            }
        }

        $topEvents = $viewData['top_events'] ?? [];
        if ($topEvents !== []) {
            $rows[] = [];
            $rows[] = ['Top events', ''];
            $rows[] = ['Name', 'Count', 'Visitors'];
            foreach ($topEvents as $event) {
                $rows[] = [$event['name'], $event['count'], $event['visitors']];
            }
        }

        return $this->rowsToCsv($rows);
    }
}
