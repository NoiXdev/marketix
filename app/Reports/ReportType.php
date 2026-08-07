<?php

namespace App\Reports;

use App\Models\Project;
use Carbon\CarbonImmutable;

/**
 * Contract implemented by every pluggable report type (project summary,
 * link, site analytics). Each type owns its own data-gathering (via the
 * existing aggregators) and the names of its email/PDF blade views.
 */
interface ReportType
{
    /**
     * Stable identifier stored on ScheduledReport::type, e.g.
     * 'project_summary'|'link'|'site_analytics'.
     */
    public function key(): string;

    /**
     * Whether $subjectId is an acceptable subject for this type within
     * $project (e.g. null for project_summary, a Url id in-project for
     * link, a Site id in-project for site_analytics).
     */
    public function validateSubject(Project $project, ?string $subjectId): bool;

    /**
     * A short human label for the subject (link slug, site name), or null
     * when the type has no distinct subject (project_summary).
     */
    public function subjectLabel(Project $project, ?string $subjectId): ?string;

    /**
     * Build the ReportData for $project (+ $subjectId) over [$start, $end].
     */
    public function gather(Project $project, ?string $subjectId, CarbonImmutable $start, CarbonImmutable $end): ReportData;

    /** Blade view name for the email body, e.g. 'reports.body.project_summary'. */
    public function emailView(): string;

    /** Blade view name for the PDF attachment, e.g. 'reports.pdf.project_summary'. */
    public function pdfView(): string;

    /**
     * Flatten $data into CSV rows; the first row is the header.
     *
     * @return array<int, array<int, string|int>>
     */
    public function csvRows(ReportData $data): array;
}
