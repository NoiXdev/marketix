<?php

namespace App\Crawler\Export;

use App\Crawler\CheckCatalog;
use App\Crawler\IssueCategory;
use App\Models\Crawl;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class CrawlXlsxExporter
{
    public function writeToFile(Crawl $crawl, string $path): void
    {
        // 1) Affected-URL count per category — one streamed pass over issues.
        $affected = [];
        foreach ($crawl->pages()->select('issues')->cursor() as $p) {
            $cats = [];
            foreach (array_unique($p->issues ?? []) as $code) {
                if (! CheckCatalog::isProblemCode($code)) {
                    continue;
                }
                $cat = CheckCatalog::categoryOf($code);
                if ($cat !== null) {
                    $cats[$cat] = true;
                }
            }
            foreach (array_keys($cats) as $cat) {
                $affected[$cat] = ($affected[$cat] ?? 0) + 1;
            }
        }

        $orderedCats = array_values(array_filter(
            array_map(fn (IssueCategory $c) => $c->value, IssueCategory::cases()),
            fn (string $c) => ($affected[$c] ?? 0) > 0,
        ));

        $writer = new Writer;
        $writer->openToFile($path);
        $bold = new Style(fontBold: true);

        // Übersicht sheet.
        $writer->getCurrentSheet()->setName($this->sheetName(__('crawler.export_overview')));
        $writer->addRow(Row::fromValuesWithStyle([__('crawler.export_col_category'), __('crawler.export_col_affected')], $bold));
        foreach ($orderedCats as $cat) {
            $writer->addRow(Row::fromValues([__('crawler.category_group.'.$cat), $affected[$cat]]));
        }

        // One sheet per affected category.
        foreach ($orderedCats as $cat) {
            $problemCodes = array_values(array_filter(
                CheckCatalog::activeCodesForCategory($cat),
                fn (string $code) => CheckCatalog::isProblemCode($code),
            ));

            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($this->sheetName(__('crawler.category_group.'.$cat)));
            $writer->addRow(Row::fromValuesWithStyle([
                __('crawler.col_url'), __('crawler.col_status'), __('crawler.col_indexable'),
                __('crawler.col_inlinks'), __('crawler.export_col_problems'),
            ], $bold));

            $query = $crawl->pages()->orderBy('depth')->orderBy('url')
                ->where(function ($w) use ($problemCodes) {
                    foreach ($problemCodes as $code) {
                        $w->orWhereJsonContains('issues', $code);
                    }
                });

            foreach ($query->cursor() as $p) {
                $present = array_values(array_intersect($problemCodes, array_unique($p->issues ?? [])));
                if ($present === []) {
                    continue;
                }
                $labels = array_map(fn (string $code) => __('crawler.issue.'.$code), $present);
                $writer->addRow(Row::fromValues([
                    $p->url,
                    $p->status_code,
                    $p->is_indexable ? __('crawler.yes') : __('crawler.no'),
                    $p->inlinks_count,
                    implode(', ', $labels),
                ]));
            }
        }

        $writer->close();
    }

    /** Excel sheet names: ≤31 chars, without : \ / ? * [ ]. */
    private function sheetName(string $label): string
    {
        $clean = trim((string) preg_replace('/[:\\\\\/?*\[\]]/', ' ', $label));

        return mb_substr($clean === '' ? 'Sheet' : $clean, 0, 31);
    }
}
