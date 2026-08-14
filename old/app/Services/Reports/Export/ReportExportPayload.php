<?php

namespace App\Services\Reports\Export;

final readonly class ReportExportPayload
{
    public function __construct(
        public string $content,
        public string $extension,
        public string $mimeType
    ) {
    }
}
