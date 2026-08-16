<?php

namespace App\Contracts;

use App\Enums\FileScanStatus;

interface FileScanner
{
    public function scan(string $absolutePath): FileScanStatus;
}
