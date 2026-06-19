<?php

namespace App\Reports;

use App\Models\Project;

class ScheduledReportData
{
    public function __construct(private readonly ReportDataService $reports) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Project $project, ReportPeriod $period): array
    {
        $current = $this->reports->forProject($project, $period->current());
        $previous = $this->reports->forProject($project, $period->previous());

        return [
            'totalClicks' => $current->totalClicks,
            'uniqueClicks' => $current->uniqueClicks,
            'clicksChange' => $this->change($current->totalClicks, $previous->totalClicks),
            'uniqueChange' => $this->change($current->uniqueClicks, $previous->uniqueClicks),
            'topLinks' => array_slice($current->topLinks, 0, 10),
            'topCountries' => array_slice($current->breakdowns['country'] ?? [], 0, 5),
            'topReferrers' => array_slice($current->breakdowns['domain'] ?? [], 0, 5),
            'timeSeries' => $current->timeSeries,
            'periodLabel' => $period->label(),
            'frequencyLabel' => $period->frequency()->label(),
        ];
    }

    /**
     * @return array{value: int, previous: int, percent: ?int, isNew: bool}
     */
    private function change(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'value' => $current,
                'previous' => 0,
                'percent' => $current > 0 ? null : 0,
                'isNew' => $current > 0,
            ];
        }

        return [
            'value' => $current,
            'previous' => $previous,
            'percent' => (int) round((($current - $previous) / $previous) * 100),
            'isNew' => false,
        ];
    }
}
