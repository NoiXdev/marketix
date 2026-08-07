<?php

namespace App\Reports;

use App\Models\Project;
use App\Services\StatisticsAggregator;
use Carbon\CarbonImmutable;

/**
 * Project-wide click summary: totals + uniques + top links + the same
 * breakdown columns as the Statistics page (country/browser/os/domain),
 * plus a daily click trend. Has no distinct subject — $subjectId must be
 * null.
 */
final class ProjectSummaryReport implements ReportType
{
    public function __construct(private readonly StatisticsAggregator $stats) {}

    public function key(): string
    {
        return 'project_summary';
    }

    public function validateSubject(Project $project, ?string $subjectId): bool
    {
        return $subjectId === null;
    }

    public function subjectLabel(Project $project, ?string $subjectId): ?string
    {
        return null;
    }

    public function gather(Project $project, ?string $subjectId, CarbonImmutable $start, CarbonImmutable $end): ReportData
    {
        $projectId = $project->id;

        $kpis = [
            ['label' => __('reports.report.total_clicks'), 'value' => (string) $this->stats->totalClicks($projectId, null, $start, $end)],
            ['label' => __('reports.report.unique_clicks'), 'value' => (string) $this->stats->uniqueClicks($projectId, null, $start, $end)],
        ];

        $breakdowns = [];

        $breakdowns[] = [
            'title' => __('reports.report.top_links'),
            'rows' => $this->stats->topLinks($projectId, $start, $end, 5)
                ->map(fn ($row) => ['label' => "/{$row->slug} ({$row->domain_name})", 'value' => (string) $row->clicks])
                ->all(),
        ];

        foreach ($this->breakdownColumns() as $column => $label) {
            $breakdowns[] = [
                'title' => $label,
                'rows' => $this->stats->breakdown($projectId, null, $column, $start, $end)
                    ->map(fn ($row) => ['label' => (string) $row->{$column}, 'value' => (string) $row->count])
                    ->all(),
            ];
        }

        $series = array_map(
            fn (array $day) => ['date' => $day['date'], 'value' => $day['clicks']],
            $this->stats->clicksByDayBetween($projectId, null, $start, $end),
        );

        return new ReportData(
            title: $project->name,
            periodLabel: $this->periodLabel($start, $end),
            kpis: $kpis,
            breakdowns: $breakdowns,
            series: $series,
        );
    }

    public function emailView(): string
    {
        return 'reports.body.project_summary';
    }

    public function pdfView(): string
    {
        return 'reports.pdf.project_summary';
    }

    public function csvRows(ReportData $data): array
    {
        $rows = [[__('reports.report.trend').' — '.__('reports.report.total_clicks'), '']];
        $rows[] = ['Date', 'Clicks'];
        foreach ($data->series as $point) {
            $rows[] = [$point['date'], $point['value']];
        }

        foreach ($data->breakdowns as $breakdown) {
            $rows[] = [];
            $rows[] = [$breakdown['title'], 'Count'];
            foreach ($breakdown['rows'] as $row) {
                $rows[] = [$row['label'], $row['value']];
            }
        }

        return $rows;
    }

    /** @return array<string, string> Statistics-page breakdown columns, keyed by their aggregator column name. */
    private function breakdownColumns(): array
    {
        return [
            'country' => __('reports.report.top_countries'),
            'browser' => __('reports.report.top_browsers'),
            'os' => __('reports.report.top_os'),
            'domain' => __('reports.report.top_referrers'),
        ];
    }

    private function periodLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->translatedFormat('j M Y').' – '.$end->translatedFormat('j M Y');
    }
}
