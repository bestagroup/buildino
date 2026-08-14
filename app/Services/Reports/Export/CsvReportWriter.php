<?php

namespace App\Services\Reports\Export;

final class CsvReportWriter implements ReportExportWriter
{
    public function __construct(
        private readonly ReportTabularizer $tabularizer
    ) {
    }

    public function write(
        string $title,
        array $data
    ): ReportExportPayload {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        /*
         * UTF-8 BOM improves Excel compatibility,
         * especially for Persian/Arabic text.
         */
        fwrite(
            $stream,
            "\xEF\xBB\xBF"
        );

        fputcsv(
            $stream,
            ['Report', $title],
            ',',
            '"',
            ''
        );

        fputcsv(
            $stream,
            [],
            ',',
            '"',
            ''
        );

        fputcsv(
            $stream,
            ['Field', 'Value'],
            ',',
            '"',
            ''
        );

        foreach (
            $this->tabularizer->rows(
                $data,
                config(
                    'report_exports.max_rows',
                    50000
                )
            )
            as $row
        ) {
            fputcsv(
                $stream,
                [
                    $row['field'],
                    $row['value'],
                ],
                ',',
                '"',
                ''
            );
        }

        rewind($stream);

        $content = stream_get_contents(
            $stream
        );

        fclose($stream);

        return new ReportExportPayload(
            $content ?: '',
            'csv',
            'text/csv; charset=UTF-8'
        );
    }
}
