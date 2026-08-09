<?php

namespace App\Reports\Csv;

/**
 * Small shared helper for ReportType::csv() implementations: turns a list
 * of row-arrays into a CSV string using the same escaping rules PHP's
 * fputcsv() applies, without needing a real filesystem handle.
 */
trait WritesCsv
{
    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function rowsToCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'w+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}
