<?php

namespace App\Console\Commands;

use App\Services\System\SystemHealthService;
use Illuminate\Console\Command;

class SystemHealthCommand extends Command
{
    protected $signature = 'system:health
        {--json : Output machine-readable JSON}
        {--fail-on-degraded : Return non-zero when status is degraded}';

    protected $description =
        'Inspect Buildino production runtime health';

    public function handle(
        SystemHealthService $health
    ): int {
        $result = $health->inspect(
            true
        );

        if ($this->option('json')) {
            $this->line(
                json_encode(
                    $result,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                )
            );
        } else {
            $this->info(
                'Buildino health: '
                .strtoupper(
                    $result['status']
                )
            );

            $rows = [];

            foreach (
                $result['checks']
                as $name => $check
            ) {
                $rows[] = [
                    $name,
                    $check['status']
                        ?? 'unknown',
                    $this->summary(
                        $check
                    ),
                ];
            }

            $this->table(
                [
                    'Check',
                    'Status',
                    'Summary',
                ],
                $rows
            );
        }

        if (! $result['ready']) {
            return self::FAILURE;
        }

        if (
            $result['status']
                === 'degraded'
            && $this->option(
                'fail-on-degraded'
            )
        ) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function summary(
        array $check
    ): string {
        if (
            isset($check['error'])
        ) {
            return (string) $check['error'];
        }

        if (
            isset($check['message'])
        ) {
            return (string) $check['message'];
        }

        if (
            isset($check['count'])
        ) {
            return 'count='
                .$check['count'];
        }

        if (
            isset($check['latency_ms'])
        ) {
            return 'latency_ms='
                .$check['latency_ms'];
        }

        if (
            isset($check['queues'])
        ) {
            return 'queues='
                .count(
                    $check['queues']
                );
        }

        return '';
    }
}
