<?php

namespace App\Services\Reports\Export;

final class ExcelReportWriter implements ReportExportWriter
{
    public function __construct(
        private readonly ReportTabularizer $tabularizer
    ) {
    }

    public function write(
        string $title,
        array $data
    ): ReportExportPayload {
        $rows = $this->tabularizer->rows(
            $data,
            config(
                'report_exports.max_rows',
                50000
            )
        );

        /*
         * SpreadsheetML 2003 is a genuine Excel-readable workbook
         * and avoids adding a third-party XLSX dependency.
         */
        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<?mso-application progid="Excel.Sheet"?>',
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"',
            ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">',
            '<Worksheet ss:Name="Report"><Table>',
            $this->row(['Report', $title]),
            $this->row(['Field', 'Value']),
        ];

        foreach ($rows as $row) {
            $xml[] = $this->row([
                $row['field'],
                $row['value'],
            ]);
        }

        $xml[] =
            '</Table></Worksheet></Workbook>';

        return new ReportExportPayload(
            implode('', $xml),
            'xls',
            'application/vnd.ms-excel'
        );
    }

    private function row(
        array $values
    ): string {
        $cells = array_map(
            fn (mixed $value): string =>
                '<Cell><Data ss:Type="String">'
                .$this->escape(
                    (string) $value
                )
                .'</Data></Cell>',
            $values
        );

        return '<Row>'
            .implode('', $cells)
            .'</Row>';
    }

    private function escape(
        string $value
    ): string {
        return htmlspecialchars(
            $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );
    }
}
