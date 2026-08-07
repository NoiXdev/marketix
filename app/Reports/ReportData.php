<?php

namespace App\Reports;

/**
 * Typed struct returned by every ReportType::gather(). Pure data — no
 * behavior, no framework dependencies. Consumed by the email/PDF blade
 * views (via emailView()/pdfView()) and by csvRows().
 */
final class ReportData
{
    /**
     * @param  array<int, array{label: string, value: string}>  $kpis
     * @param  array<int, array{title: string, rows: array<int, array{label: string, value: string}>}>  $breakdowns
     * @param  array<int, array{date: string, value: int}>  $series
     */
    public function __construct(
        public readonly string $title,
        public readonly string $periodLabel,
        public readonly array $kpis,
        public readonly array $breakdowns,
        public readonly array $series,
    ) {}
}
