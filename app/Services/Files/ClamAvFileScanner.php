<?php

namespace App\Services\Files;

use App\Contracts\FileScanner;
use App\Enums\FileScanStatus;
use Illuminate\Support\Facades\Process;

final class ClamAvFileScanner implements FileScanner
{
    public function __construct(
        private readonly string $binary,
        private readonly int $timeoutSeconds
    ) {
    }

    public function scan(string $absolutePath): FileScanStatus
    {
        $result = Process::timeout($this->timeoutSeconds)
            ->run([
                $this->binary,
                '--no-summary',
                $absolutePath,
            ]);

        return match ($result->exitCode()) {
            0 => FileScanStatus::Clean,
            1 => FileScanStatus::Infected,
            default => FileScanStatus::Failed,
        };
    }
}
