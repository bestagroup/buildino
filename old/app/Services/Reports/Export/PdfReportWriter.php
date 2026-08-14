<?php

namespace App\Services\Reports\Export;

final class PdfReportWriter implements ReportExportWriter
{
    public function __construct(
        private readonly ReportTabularizer $tabularizer
    ) {
    }

    public function write(
        string $title,
        array $data
    ): ReportExportPayload {
        /*
         * If Dompdf is installed in the host project, use it for
         * full Unicode/RTL support. The export infrastructure itself
         * does not require Dompdf to be installed.
         */
        if (class_exists(\Dompdf\Dompdf::class)) {
            return $this->withDompdf(
                $title,
                $data
            );
        }

        return $this->nativePdf(
            $title,
            $data
        );
    }

    private function withDompdf(
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

        $html =
            '<!doctype html><html><head><meta charset="UTF-8">'
            .'<style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:10px;}'
            .'h1{font-size:16px;}'
            .'table{width:100%;border-collapse:collapse;}'
            .'th,td{border:1px solid #ccc;padding:5px;vertical-align:top;}'
            .'th{text-align:left;}'
            .'</style></head><body>';

        $html .= '<h1>'
            .htmlspecialchars(
                $title,
                ENT_QUOTES,
                'UTF-8'
            )
            .'</h1>';

        $html .=
            '<table><thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr><td>'
                .htmlspecialchars(
                    $row['field'],
                    ENT_QUOTES,
                    'UTF-8'
                )
                .'</td><td>'
                .htmlspecialchars(
                    $row['value'],
                    ENT_QUOTES,
                    'UTF-8'
                )
                .'</td></tr>';
        }

        $html .= '</tbody></table></body></html>';

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
        ]);

        $dompdf->loadHtml(
            $html,
            'UTF-8'
        );

        $dompdf->setPaper(
            'A4',
            'portrait'
        );

        $dompdf->render();

        return new ReportExportPayload(
            $dompdf->output(),
            'pdf',
            'application/pdf'
        );
    }

    private function nativePdf(
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

        $truncated = count($rows) > 1200;

        if ($truncated) {
            $rows = array_slice(
                $rows,
                0,
                1200
            );
        }

        $lines = [
            $title,
            str_repeat('-', 80),
        ];

        foreach ($rows as $row) {
            $lines[] =
                $this->ascii(
                    $row['field']
                    .' : '
                    .$row['value']
                );
        }

        if ($truncated) {
            $lines[] =
                '[Native PDF fallback truncated after 1200 rows. CSV/Excel retain the full report.]';
        }

        $pages = array_chunk(
            $lines,
            48
        );

        $objects = [];
        $pageObjectIds = [];
        $fontObjectId = 3;
        $nextObjectId = 4;

        foreach ($pages as $pageLines) {
            $pageId = $nextObjectId++;
            $contentId = $nextObjectId++;

            $pageObjectIds[] = $pageId;

            $content = "BT\n/F1 9 Tf\n45 800 Td\n";

            foreach ($pageLines as $index => $line) {
                if ($index > 0) {
                    $content .= "0 -15 Td\n";
                }

                $content .= '('
                    .$this->pdfEscape(
                        mb_substr(
                            $line,
                            0,
                            110
                        )
                    )
                    .") Tj\n";
            }

            $content .= "ET\n";

            $objects[$pageId] =
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
                ."/Resources << /Font << /F1 {$fontObjectId} 0 R >> >> "
                ."/Contents {$contentId} 0 R >>";

            $objects[$contentId] =
                "<< /Length "
                .strlen($content)
                ." >>\nstream\n"
                .$content
                ."endstream";
        }

        $kids = implode(
            ' ',
            array_map(
                fn (int $id): string =>
                    "{$id} 0 R",
                $pageObjectIds
            )
        );

        $objects[1] =
            "<< /Type /Catalog /Pages 2 0 R >>";

        $objects[2] =
            "<< /Type /Pages /Kids [{$kids}] /Count "
            .count($pageObjectIds)
            ." >>";

        $objects[3] =
            "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] =
                strlen($pdf);

            $pdf .= "{$id} 0 obj\n"
                .$body
                ."\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));

        $pdf .= "xref\n0 "
            .($maxId + 1)
            ."\n";

        $pdf .=
            "0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf(
                "%010d 00000 n \n",
                $offsets[$id] ?? 0
            );
        }

        $pdf .=
            "trailer\n<< /Size "
            .($maxId + 1)
            ." /Root 1 0 R >>\n"
            ."startxref\n{$xref}\n%%EOF";

        return new ReportExportPayload(
            $pdf,
            'pdf',
            'application/pdf'
        );
    }

    private function ascii(
        string $value
    ): string {
        $converted = function_exists('iconv')
            ? iconv(
                'UTF-8',
                'ASCII//TRANSLIT//IGNORE',
                $value
            )
            : $value;

        return is_string($converted)
            ? $converted
            : $value;
    }

    private function pdfEscape(
        string $value
    ): string {
        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', '', ' '],
            $value
        );
    }
}
