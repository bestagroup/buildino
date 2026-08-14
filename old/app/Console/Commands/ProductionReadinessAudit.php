<?php

namespace App\Console\Commands;

use App\Services\System\SystemHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ProductionReadinessAudit extends Command
{
    protected $signature = 'system:production-audit
        {--strict : Treat warnings as failures}';

    protected $description =
        'Audit Buildino configuration and runtime readiness before production deployment';

    public function handle(
        SystemHealthService $health
    ): int {
        $findings = [];

        $this->finding(
            $findings,
            'APP_KEY',
            filled(config('app.key')),
            'critical',
            'Application encryption key must be configured.'
        );

        $isProduction =
            app()->environment(
                'production'
            );

        if ($isProduction) {
            $this->finding(
                $findings,
                'APP_DEBUG',
                config('app.debug') === false,
                'critical',
                'APP_DEBUG must be false in production.'
            );

            $this->finding(
                $findings,
                'APP_URL HTTPS',
                str_starts_with(
                    strtolower(
                        (string) config(
                            'app.url'
                        )
                    ),
                    'https://'
                ),
                'critical',
                'APP_URL should use HTTPS in production.'
            );
        } else {
            $findings[] = [
                'check' => 'Environment',
                'status' => 'warning',
                'message' =>
                    'Application is not currently running with APP_ENV=production.',
            ];
        }

        $queueConnection =
            (string) config(
                'queue.default'
            );

        $queueDriver =
            (string) config(
                "queue.connections.{$queueConnection}.driver",
                $queueConnection
            );

        $this->finding(
            $findings,
            'Queue driver',
            ! in_array(
                $queueDriver,
                [
                    'sync',
                    'deferred',
                    'background',
                ],
                true
            ),
            $isProduction
                ? 'critical'
                : 'warning',
            'Production should use a persistent queue driver such as database/redis/sqs.'
        );

        $cacheStore =
            (string) config(
                'cache.default'
            );

        $this->finding(
            $findings,
            'Cache store',
            ! in_array(
                $cacheStore,
                [
                    'array',
                    'null',
                ],
                true
            ),
            $isProduction
                ? 'critical'
                : 'warning',
            'Production should use a persistent/shared cache store.'
        );

        $runtimeTables = [
            'jobs',
            'failed_jobs',
            'job_batches',
            'cache',
            'cache_locks',
            'system_runtime_heartbeats',
        ];

        foreach ($runtimeTables as $table) {
            $this->finding(
                $findings,
                'Table: '.$table,
                Schema::hasTable(
                    $table
                ),
                'critical',
                "Runtime table [{$table}] is missing."
            );
        }

        foreach (
            [
                'openssl',
                'pdo',
                'mbstring',
            ]
            as $extension
        ) {
            $this->finding(
                $findings,
                'PHP extension: '
                    .$extension,
                extension_loaded(
                    $extension
                ),
                'critical',
                "Required PHP extension [{$extension}] is missing."
            );
        }

        $gatewayEnabled =
            (bool) config(
                'payment_gateways.gateways.generic.enabled',
                false
            );

        if ($gatewayEnabled) {
            foreach (
                [
                    'request_url',
                    'verify_url',
                    'merchant_id',
                    'secret',
                    'webhook_secret',
                ]
                as $key
            ) {
                $value = config(
                    "payment_gateways.gateways.generic.{$key}"
                );

                $this->finding(
                    $findings,
                    'Gateway: '.$key,
                    is_string($value)
                        && $value !== '',
                    'critical',
                    "Enabled payment gateway requires [{$key}]."
                );
            }

            if ($isProduction) {
                foreach (
                    [
                        'request_url',
                        'verify_url',
                    ]
                    as $key
                ) {
                    $value = strtolower(
                        (string) config(
                            "payment_gateways.gateways.generic.{$key}"
                        )
                    );

                    $this->finding(
                        $findings,
                        'Gateway HTTPS: '
                            .$key,
                        str_starts_with(
                            $value,
                            'https://'
                        ),
                        'critical',
                        "Production gateway [{$key}] must use HTTPS."
                    );
                }
            }
        }

        if ($isProduction) {
            $smsProvider = (string) config(
                'notifications.sms_provider',
                'log'
            );

            $pushProvider = (string) config(
                'notifications.push_provider',
                'log'
            );

            $this->finding(
                $findings,
                'SMS provider',
                $smsProvider !== 'log',
                'warning',
                'Production SMS provider is still configured as log.'
            );

            $this->finding(
                $findings,
                'Push provider',
                $pushProvider !== 'log',
                'warning',
                'Production Push provider is still configured as log.'
            );

            $mailProvider = (string) config(
                'mail.default',
                'log'
            );

            $this->finding(
                $findings,
                'Mail provider',
                ! in_array($mailProvider, ['log', 'array'], true),
                'warning',
                'Production Mail provider is not configured for real delivery.'
            );

            if ($smsProvider === 'http') {
                $this->finding(
                    $findings,
                    'SMS HTTP endpoint',
                    str_starts_with(
                        strtolower((string) config('notifications.http_sms.endpoint')),
                        'https://'
                    ),
                    'critical',
                    'Production SMS HTTP endpoint must use HTTPS.'
                );
            }

            if ($pushProvider === 'http') {
                $this->finding(
                    $findings,
                    'Push HTTP endpoint',
                    str_starts_with(
                        strtolower((string) config('notifications.http_push.endpoint')),
                        'https://'
                    ),
                    'critical',
                    'Production Push HTTP endpoint must use HTTPS.'
                );
            }
        }

        $runtime =
            $health->inspect(
                true
            );

        $findings[] = [
            'check' =>
                'Runtime health',
            'status' =>
                $runtime['status']
                    === 'ok'
                    ? 'pass'
                    : (
                        $runtime['ready']
                            ? 'warning'
                            : 'critical'
                    ),
            'message' =>
                'Runtime status='
                .$runtime['status'],
        ];

        $this->table(
            [
                'Check',
                'Status',
                'Message',
            ],
            array_map(
                fn (array $item): array => [
                    $item['check'],
                    strtoupper(
                        $item['status']
                    ),
                    $item['message'],
                ],
                $findings
            )
        );

        $critical = collect(
            $findings
        )
            ->where(
                'status',
                'critical'
            )
            ->count();

        $warnings = collect(
            $findings
        )
            ->where(
                'status',
                'warning'
            )
            ->count();

        $this->newLine();

        $this->line(
            "Critical={$critical}, Warnings={$warnings}"
        );

        if ($critical > 0) {
            return self::FAILURE;
        }

        if (
            $warnings > 0
            && $this->option(
                'strict'
            )
        ) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function finding(
        array &$findings,
        string $check,
        bool $passed,
        string $severity,
        string $failureMessage
    ): void {
        $findings[] = [
            'check' => $check,
            'status' =>
                $passed
                    ? 'pass'
                    : $severity,
            'message' =>
                $passed
                    ? 'OK'
                    : $failureMessage,
        ];
    }
}
