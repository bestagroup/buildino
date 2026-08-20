<?php

namespace App\Providers;

use App\Contracts\FileScanner;
use App\Services\Files\ClamAvFileScanner;
use App\Services\Files\ClamAvTcpFileScanner;
use App\Services\Files\DisabledFileScanner;
use Illuminate\Support\ServiceProvider;

final class FileManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            FileScanner::class,
            function (): FileScanner {
                if (! config('file_management.scan.enabled')) {
                    return new DisabledFileScanner();
                }

                if (config('file_management.scan.driver') === 'clamd_tcp') {
                    return new ClamAvTcpFileScanner(
                        (string) config('file_management.scan.host'),
                        (int) config('file_management.scan.port'),
                        (int) config(
                            'file_management.scan.timeout_seconds',
                            30
                        )
                    );
                }

                return new ClamAvFileScanner(
                    (string) config(
                        'file_management.scan.binary',
                        'clamdscan'
                    ),
                    (int) config(
                        'file_management.scan.timeout_seconds',
                        30
                    )
                );
            }
        );
    }
}
