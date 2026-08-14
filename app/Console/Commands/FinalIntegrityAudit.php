<?php

namespace App\Console\Commands;

use App\Services\System\FinalIntegrityAuditService;
use Illuminate\Console\Command;

class FinalIntegrityAudit extends Command
{
    protected $signature = 'system:integrity-audit
        {--json : Output machine-readable JSON}
        {--strict : Treat warnings as failure}';

    protected $description =
        'Audit final Buildino financial, notification and support data integrity';

    public function handle(FinalIntegrityAuditService $audit): int
    {
        $result = $audit->inspect();

        if ($this->option('json')) {
            $this->line(json_encode(
                $result,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ));
        } else {
            $this->table(
                ['Check', 'Severity', 'Count'],
                array_map(
                    fn (array $check): array => [
                        $check['name'],
                        strtoupper($check['severity']),
                        $check['skipped'] ?? false
                            ? 'SKIPPED'
                            : $check['count'],
                    ],
                    $result['checks']
                )
            );

            $this->line(
                "Critical={$result['critical_count']}, Warnings={$result['warning_count']}"
            );
        }

        if (! $result['ok']) {
            return self::FAILURE;
        }

        if (
            $this->option('strict')
            && $result['warning_count'] > 0
        ) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
