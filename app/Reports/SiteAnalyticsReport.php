<?php

namespace App\Reports;

use App\Models\Project;
use App\Services\AnalyticsAggregator;
use App\Services\GoalAggregator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Site analytics summary mirroring AnalyticsController@show: visits, page
 * views, goal conversions and top events for the window. Subject must be a
 * Site id belonging to the project.
 *
 * AnalyticsAggregator/GoalAggregator only support a trailing "last N days
 * ending now()" window (no absolute [start, end] range), unlike
 * StatisticsAggregator. [$start, $end] is therefore converted to a day
 * count and passed through as-is — for the "previous_month" period this is
 * an approximation (a trailing N-day window ending today, not the exact
 * historical month), consistent with how the Analytics page itself always
 * windows relative to now().
 */
final class SiteAnalyticsReport implements ReportType
{
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

    public function gather(Project $project, ?string $subjectId, CarbonImmutable $start, CarbonImmutable $end): ReportData
    {
        if ($subjectId === null) {
            throw new InvalidArgumentException('SiteAnalyticsReport requires a subject id.');
        }

        $site = $project->sites()->whereKey($subjectId)->firstOrFail();
        $days = $this->daysBetween($start, $end);

        $kpis = [
            ['label' => __('reports.report.visits'), 'value' => (string) $this->analytics->totalSessions($site->id, $days)],
            ['label' => __('reports.report.page_views'), 'value' => (string) $this->analytics->totalPageViews($site->id, $days)],
        ];

        $breakdowns = [];

        $breakdowns[] = [
            'title' => __('reports.report.goals'),
            'rows' => $site->goals()->get()->map(function ($goal) use ($days) {
                $conversions = $this->goals->conversions($goal, $days);

                return [
                    'label' => $goal->name,
                    'value' => "{$conversions['conversions']} ({$conversions['rate']}%)",
                ];
            })->all(),
        ];

        $breakdowns[] = [
            'title' => __('reports.report.top_events'),
            'rows' => $this->goals->topEvents($site->id, $days, 8)
                ->map(fn ($row) => ['label' => $row->name, 'value' => (string) $row->count])
                ->all(),
        ];

        $series = array_map(
            fn (array $day) => ['date' => $day['date'], 'value' => $day['views']],
            $this->analytics->pageViewsByDay($site->id, $days),
        );

        return new ReportData(
            title: $site->name,
            periodLabel: $this->periodLabel($start, $end),
            kpis: $kpis,
            breakdowns: $breakdowns,
            series: $series,
        );
    }

    public function emailView(): string
    {
        return 'reports.body.site_analytics';
    }

    public function pdfView(): string
    {
        return 'reports.pdf.site_analytics';
    }

    public function csvRows(ReportData $data): array
    {
        $rows = [[__('reports.report.trend').' — '.__('reports.report.page_views'), '']];
        $rows[] = ['Date', 'Page views'];
        foreach ($data->series as $point) {
            $rows[] = [$point['date'], $point['value']];
        }

        foreach ($data->breakdowns as $breakdown) {
            $rows[] = [];
            $rows[] = [$breakdown['title'], 'Value'];
            foreach ($breakdown['rows'] as $row) {
                $rows[] = [$row['label'], $row['value']];
            }
        }

        return $rows;
    }

    private function daysBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return max(1, $start->startOfDay()->diffInDays($end->startOfDay()) + 1);
    }

    private function periodLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y');
    }
}
