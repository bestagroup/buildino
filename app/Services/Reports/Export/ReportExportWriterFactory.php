<?php

namespace App\Services\Reports\Export;

use App\Enums\ReportFormat;

final class ReportExportWriterFactory
{
    public function __construct(
        private readonly CsvReportWriter $csv,
        private readonly ExcelReportWriter $excel,
        private readonly PdfReportWriter $pdf
    ) {
    }

    public function for(
        ReportFormat $format
    ): ReportExportWriter {
        return match ($format) {
            ReportFormat::Csv =>
                $this->csv,

            ReportFormat::Excel =>
                $this->excel,

            ReportFormat::Pdf =>
                $this->pdf,
        };
    }
}
