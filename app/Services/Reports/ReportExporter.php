<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes a built report to CSV, Excel (XLSX), or PDF.
 */
class ReportExporter
{
    /**
     * Export the report in the requested format.
     *
     * @param  array{title: string, columns: array<string, string>, rows: array<int, array<string, string>>, summary: array<string, int|float|string>}  $report
     */
    public function export(array $report, string $format, string $periodLabel, string $timezone): Response
    {
        $filename = Str::slug($report['title']).'-'.now()->format('Ymd-His');

        if ($format === 'pdf') {
            return Pdf::loadView('pdf.report', [
                'report' => $report,
                'period' => $periodLabel,
                'timezone' => $timezone,
            ])->setPaper('a4', 'landscape')->download("{$filename}.pdf");
        }

        $path = tempnam(sys_get_temp_dir(), 'report');
        $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_values($report['columns'])));

        foreach ($report['rows'] as $row) {
            $writer->addRow(Row::fromValues(array_map(
                fn (string $key) => $this->neutralizeFormula($row[$key] ?? ''),
                array_keys($report['columns']),
            )));
        }

        $writer->close();

        return (new BinaryFileResponse($path, 200, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]))->setContentDisposition('attachment', "{$filename}.{$format}")->deleteFileAfterSend();
    }

    /**
     * Prevent spreadsheet formula injection from user-entered text.
     */
    private function neutralizeFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
