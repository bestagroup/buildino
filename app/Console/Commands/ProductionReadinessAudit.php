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
                'fileinfo',
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

        $this->finding(
            $findings,
            'Private file disk',
            (string) config('file_management.disk') === 'private',
            'critical',
            'Managed documents must use the dedicated private filesystem disk.'
        );

        $this->finding(
            $findings,
            'Malware scanning',
            ! $isProduction
                || (bool) config(
                    'file_management.scan.enabled',
                    false
                ),
            'critical',
            'FILE_SCAN_ENABLED must be true in production.'
        );

        $scanDriver = (string) config(
            'file_management.scan.driver',
            'binary'
        );

        $this->finding(
            $findings,
            'Malware scanner driver',
            ! $isProduction
                || in_array($scanDriver, ['binary', 'clamd_tcp'], true),
            'critical',
            'FILE_SCAN_DRIVER must be binary or clamd_tcp.'
        );

        if ($isProduction && $scanDriver === 'clamd_tcp') {
            $this->finding(
                $findings,
                'ClamAV TCP host',
                trim((string) config('file_management.scan.host')) !== '',
                'critical',
                'FILE_SCAN_HOST is required for clamd_tcp.'
            );

            $scanPort = (int) config('file_management.scan.port');
            $this->finding(
                $findings,
                'ClamAV TCP port',
                $scanPort >= 1 && $scanPort <= 65535,
                'critical',
                'FILE_SCAN_PORT must be a valid TCP port.'
            );
        }

        if (
            $isProduction
            && ($queueDriver === 'redis' || $cacheStore === 'redis')
        ) {
            $this->finding(
                $findings,
                'PHP extension: redis',
                extension_loaded('redis'),
                'critical',
                'The phpredis extension is required by the configured Redis client.'
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

        $defaultGateway =
            (string) config(
                'payment_gateways.default',
                ''
            );

        $defaultGatewayConfig =
            (array) config(
                "payment_gateways.gateways.{$defaultGateway}",
                []
            );

        if ($defaultGateway !== '') {
            $this->finding(
                $findings,
                'Default payment gateway exists',
                $defaultGatewayConfig !== [],
                'critical',
                'PAYMENT_GATEWAY_DEFAULT points to an unknown gateway.'
            );

            if ($isProduction) {
                $this->finding(
                    $findings,
                    'Default payment gateway enabled',
                    (bool) (
                        $defaultGatewayConfig[
                            'enabled'
                        ]
                        ?? false
                    ),
                    'warning',
                    'Default payment gateway is disabled; online payments will not be available.'
                );

                $this->finding(
                    $findings,
                    'Default payment gateway driver',
                    (
                        $defaultGatewayConfig[
                            'driver'
                        ]
                        ?? ''
                    ) !== 'fake',
                    'critical',
                    'Fake payment driver must never be enabled in production.'
                );

                $this->finding(
                    $findings,
                    'Payment callback HTTPS',
                    str_starts_with(
                        strtolower(
                            (string) config(
                                'payment_gateways.callback_base_url',
                                ''
                            )
                        ),
                        'https://'
                    ),
                    'critical',
                    'PAYMENT_GATEWAY_CALLBACK_BASE_URL must use HTTPS in production.'
                );
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

                $this->finding(
                    $findings,
                    'SMS HTTP credentials',
                    trim(
                        (string) config(
                            'notifications.http_sms.token',
                            ''
                        )
                    ) !== '',
                    'warning',
                    'SMS_HTTP_TOKEN is empty; verify that your SMS provider really uses unauthenticated requests.'
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

            if ($pushProvider === 'fcm_v1') {
                $projectId =
                    trim(
                        (string) config(
                            'notifications.fcm.project_id',
                            ''
                        )
                    );

                $credentialsPath =
                    trim(
                        (string) config(
                            'notifications.fcm.credentials_path',
                            ''
                        )
                    );

                $credentialsBase64 =
                    trim(
                        (string) config(
                            'notifications.fcm.credentials_json_base64',
                            ''
                        )
                    );

                $this->finding(
                    $findings,
                    'FCM project id',
                    $projectId !== '',
                    'critical',
                    'FCM_PROJECT_ID is required when PUSH_PROVIDER=fcm_v1.'
                );

                $this->finding(
                    $findings,
                    'FCM service account',
                    $credentialsBase64 !== ''
                    || (
                        $credentialsPath !== ''
                        && is_file(
                            $credentialsPath
                        )
                    ),
                    'critical',
                    'FCM service-account credentials are missing or unreadable.'
                );
            }

            $this->finding(
                $findings,
                'Notification queue name',
                trim(
                    (string) config(
                        'notifications.queue',
                        ''
                    )
                ) !== '',
                'critical',
                'NOTIFICATION_QUEUE must be configured.'
            );
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
