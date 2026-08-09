<?php

namespace App\Reports;

use App\Models\Project;
use App\Reports\Csv\WritesCsv;
use InvalidArgumentException;

/**
 * Thin adapter over the existing ReportDataService::forUrl() (the same data
 * used by the on-demand /reports/download/link/{url} PDF). Subject must be
 * a Url id belonging to the project.
 */
final class LinkReport implements ReportType
{
    use WritesCsv;

    public function __construct(private readonly ReportDataService $reports) {}

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

    public function title(Project $project, ?string $subjectId): string
    {
        if ($subjectId === null) {
            throw new InvalidArgumentException('LinkReport requires a subject id.');
        }

        $slug = $project->urls()->whereKey($subjectId)->value('slug');

        return "Link report — /{$slug}";
    }

    public function viewData(Project $project, ?string $subjectId, ReportDateRange $range): array
    {
        if ($subjectId === null) {
            throw new InvalidArgumentException('LinkReport requires a subject id.');
        }

        $url = $project->urls()->find($subjectId);

        if ($url === null) {
            throw new InvalidArgumentException('LinkReport subject id does not resolve to a Url in this project.');
        }

        return $this->reports->forUrl($url, $range)->toArray();
    }

    public function pdfView(): string
    {
        return 'reports.link';
    }

    public function emailView(): string
    {
        return 'reports.email.link';
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

        $recentClicks = $viewData['recentClicks'] ?? [];
        if ($recentClicks !== []) {
            $rows[] = [];
            $rows[] = ['Recent clicks', ''];
            $rows[] = ['When', 'Country', 'City', 'Browser', 'OS'];
            foreach ($recentClicks as $click) {
                $rows[] = [$click['created_at'], $click['country'] ?? '', $click['city'] ?? '', $click['browser'] ?? '', $click['os'] ?? ''];
            }
        }

        return $this->rowsToCsv($rows);
    }
}
