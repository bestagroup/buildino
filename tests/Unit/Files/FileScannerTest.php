<?php

namespace Tests\Unit\Files;

use App\Enums\FileScanStatus;
use App\Services\Files\ClamAvFileScanner;
use App\Services\Files\DisabledFileScanner;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FileScannerTest extends TestCase
{
    public function test_disabled_scanner_marks_local_test_file_clean(): void
    {
        $this->assertSame(
            FileScanStatus::Clean,
            (new DisabledFileScanner())->scan(
                '/tmp/not-executed-by-disabled-scanner'
            )
        );
    }

    public function test_clamav_exit_codes_are_mapped_to_domain_status(): void
    {
        foreach (
            [
                0 => FileScanStatus::Clean,
                1 => FileScanStatus::Infected,
                2 => FileScanStatus::Failed,
            ]
            as $exitCode => $expected
        ) {
            Process::fake([
                '*' => Process::result(
                    exitCode: $exitCode
                ),
            ]);

            $actual = (new ClamAvFileScanner(
                'clamdscan',
                10
            ))->scan('/tmp/example.pdf');

            $this->assertSame(
                $expected,
                $actual
            );
        }
    }
}
