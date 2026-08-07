<?php

namespace App\Reports;

use App\Models\Project;

/**
 * Contract implemented by every pluggable report type (project summary,
 * link, site analytics). Each type is a thin adapter over the existing
 * report/analytics subsystem (ReportDataService, AnalyticsAggregator,
 * GoalAggregator) — it does not gather data itself.
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
     * A human-readable title for the report (project name, link slug, site
     * name).
     */
    public function title(Project $project, ?string $subjectId): string;

    /**
     * Template variables for BOTH the PDF view and the email view, built
     * for $project (+ $subjectId) over $range.
     *
     * @return array<string, mixed>
     */
    public function viewData(Project $project, ?string $subjectId, ReportDateRange $range): array;

    /** Blade view name for the PDF attachment, e.g. 'reports.project'. */
    public function pdfView(): string;

    /** Blade view name for the email body, e.g. 'reports.email.project'. */
    public function emailView(): string;

    /**
     * Serialize $viewData (as produced by viewData()) into a CSV string.
     *
     * @param  array<string, mixed>  $viewData
     */
    public function csv(array $viewData): string;
}
