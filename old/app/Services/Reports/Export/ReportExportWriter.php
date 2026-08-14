<?php

namespace App\Services\Reports\Export;

interface ReportExportWriter
{
    public function write(
        string $title,
        array $data
    ): ReportExportPayload;
}
