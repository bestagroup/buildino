<?php

namespace App\Services\Files;

use App\Contracts\FileScanner;
use App\Enums\FileScanStatus;

final class DisabledFileScanner implements FileScanner
{
    public function scan(string $absolutePath): FileScanStatus
    {
        return FileScanStatus::Clean;
    }
}
