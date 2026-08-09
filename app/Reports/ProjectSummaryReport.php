<?php

namespace App\Reports;

use App\Models\Project;
use App\Reports\Csv\WritesCsv;

/**
 * Thin adapter over the existing ReportDataService::forProject() (the same
 * data used by the on-demand /reports/download/project PDF). Has no
 * distinct subject — $subjectId must be null.
 */
final class ProjectSummaryReport implements ReportType
{
    use WritesCsv;

    public function __construct(private readonly ReportDataService $reports) {}

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

    public function title(Project $project, ?string $subjectId): string
    {
        return $project->name;
    }

    public function viewData(Project $project, ?string $subjectId, ReportDateRange $range): array
    {
        return $this->reports->forProject($project, $range)->toArray();
    }

    public function pdfView(): string
    {
        return 'reports.project';
    }

    public function emailView(): string
    {
        return 'reports.email.project';
    }

    public function csv(array $viewData): string
    {
        $rows = [
            [$viewData['title'] ?? '', $viewData['rangeLabel'] ?? ''],
            ['Total clicks', $viewData['totalClicks'] ?? 0],
            ['Unique clicks', $viewData['uniqueClicks'] ?? 0],
            [],
            ['Date', 'Clicks', 'Unique'],
        ];

        foreach ($viewData['timeSeries'] ?? [] as $point) {
            $rows[] = [$point['date'], $point['clicks'], $point['unique']];
        }

        $topLinks = $viewData['topLinks'] ?? [];
        if ($topLinks !== []) {
            $rows[] = [];
            $rows[] = ['Top links', ''];
            $rows[] = ['Link', 'Clicks'];
            foreach ($topLinks as $link) {
                $rows[] = ["{$link['domain']}/{$link['slug']}", $link['clicks']];
            }
        }

        foreach ($viewData['breakdowns'] ?? [] as $column => $breakdownRows) {
            if ($breakdownRows === []) {
                continue;
            }

            $rows[] = [];
            $rows[] = [ucfirst($column), 'Count'];
            foreach ($breakdownRows as $row) {
                $rows[] = [$row['label'], $row['count']];
            }
        }

        return $this->rowsToCsv($rows);
    }
}
