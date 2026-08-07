<?php

namespace App\Reports;

use App\Models\Project;
use App\Services\StatisticsAggregator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Single-link click summary: same shape as ProjectSummaryReport (totals,
 * uniques, breakdowns, trend) but scoped to one Url within the project, and
 * without a top-links breakdown. Subject must be a Url id belonging to the
 * project.
 */
final class LinkReport implements ReportType
{
    public function __construct(private readonly StatisticsAggregator $stats) {}

    public function key(): string
    {
        return 'link';
    }

    public function validateSubject(Project $project, ?string $subjectId): bool
    {
        return $subjectId !== null && $project->urls()->whereKey($subjectId)->exists();
    }

    public function subjectLabel(Project $project, ?string $subjectId): ?string
    {
        if ($subjectId === null) {
            return null;
        }

        return $project->urls()->whereKey($subjectId)->value('slug');
    }

    public function gather(Project $project, ?string $subjectId, CarbonImmutable $start, CarbonImmutable $end): ReportData
    {
        if ($subjectId === null) {
            throw new InvalidArgumentException('LinkReport requires a subject id.');
        }

        $url = $project->urls()->whereKey($subjectId)->firstOrFail();
        $projectId = $project->id;

        $kpis = [
            ['label' => __('reports.report.total_clicks'), 'value' => (string) $this->stats->totalClicks($projectId, $url->id, $start, $end)],
            ['label' => __('reports.report.unique_clicks'), 'value' => (string) $this->stats->uniqueClicks($projectId, $url->id, $start, $end)],
        ];

        $breakdowns = [];
        foreach ($this->breakdownColumns() as $column => $label) {
            $breakdowns[] = [
                'title' => $label,
                'rows' => $this->stats->breakdown($projectId, $url->id, $column, $start, $end)
                    ->map(fn ($row) => ['label' => (string) $row->{$column}, 'value' => (string) $row->count])
                    ->all(),
            ];
        }

        $series = array_map(
            fn (array $day) => ['date' => $day['date'], 'value' => $day['clicks']],
            $this->stats->clicksByDayBetween($projectId, $url->id, $start, $end),
        );

        return new ReportData(
            title: $url->slug ?: $url->url,
            periodLabel: $this->periodLabel($start, $end),
            kpis: $kpis,
            breakdowns: $breakdowns,
            series: $series,
        );
    }

    public function emailView(): string
    {
        return 'reports.body.link';
    }

    public function pdfView(): string
    {
        return 'reports.pdf.link';
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
