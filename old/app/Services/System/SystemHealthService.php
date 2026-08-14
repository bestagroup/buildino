<?php

namespace App\Services\System;

use App\Enums\PaymentGatewayEventStatus;
use App\Enums\NotificationStatus;
use App\Enums\ReportStatus;
use App\Enums\WalletAccountingPostingStatus;
use App\Models\GeneratedReport;
use App\Models\NotificationLog;
use App\Models\PaymentGatewayEvent;
use App\Models\WalletAccountingPosting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class SystemHealthService
{
    public function __construct(
        private readonly RuntimeHeartbeatService $heartbeats
    ) {
    }

    public function inspect(
        bool $detailed = true
    ): array {
        $checks = [
            'database' =>
                $this->databaseCheck(),

            'cache' =>
                $this->cacheCheck(),

            'storage' =>
                $this->storageCheck(),

            'scheduler' =>
                $this->schedulerCheck(),

            'queues' =>
                $this->queueCheck(),
        ];

        if ($detailed) {
            $checks['failed_jobs'] =
                $this->failedJobsCheck();

            $checks['domain'] =
                $this->domainCheck();
        }

        $criticalReady =
            $checks['database']['status'] === 'ok'
            && $checks['cache']['status'] === 'ok'
            && $checks['storage']['status'] === 'ok';

        $hasWarnings = collect($checks)
            ->contains(
                fn (array $check): bool =>
                    in_array(
                        $check['status'] ?? 'ok',
                        [
                            'warning',
                            'critical',
                            'fail',
                        ],
                        true
                    )
            );

        $status = ! $criticalReady
            ? 'not_ready'
            : (
                $hasWarnings
                    ? 'degraded'
                    : 'ok'
            );

        return [
            'status' => $status,
            'ready' => $criticalReady,
            'environment' =>
                app()->environment(),
            'application' =>
                config('app.name'),
            'timestamp' =>
                now()->toISOString(),
            'checks' => $checks,
        ];
    }

    public function publicReadiness(): array
    {
        $health = $this->inspect(
            false
        );

        return [
            'status' =>
                $health['status'],
            'ready' =>
                $health['ready'],
            'timestamp' =>
                $health['timestamp'],
        ];
    }

    private function databaseCheck(): array
    {
        $started = microtime(true);

        try {
            DB::select('select 1');

            return [
                'status' => 'ok',
                'latency_ms' =>
                    $this->latency(
                        $started
                    ),
                'connection' =>
                    DB::connection()
                        ->getDriverName(),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'fail',
                'latency_ms' =>
                    $this->latency(
                        $started
                    ),
                'error' =>
                    $this->message(
                        $exception
                    ),
            ];
        }
    }

    private function cacheCheck(): array
    {
        $started = microtime(true);

        try {
            $key =
                'health:'
                .Str::uuid();

            Cache::put(
                $key,
                'ok',
                30
            );

            $value = Cache::get(
                $key
            );

            Cache::forget(
                $key
            );

            if ($value !== 'ok') {
                throw new \RuntimeException(
                    'Cache round-trip verification failed.'
                );
            }

            return [
                'status' => 'ok',
                'latency_ms' =>
                    $this->latency(
                        $started
                    ),
                'store' =>
                    config('cache.default'),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'fail',
                'latency_ms' =>
                    $this->latency(
                        $started
                    ),
                'store' =>
                    config('cache.default'),
                'error' =>
                    $this->message(
                        $exception
                    ),
            ];
        }
    }

    private function storageCheck(): array
    {
        $started = microtime(true);

        $disk = (string) config(
            'production_readiness.health.storage_disk',
            'local'
        );

        $path =
            '.health/'
            .Str::uuid()
            .'.txt';

        try {
            $storage = Storage::disk(
                $disk
            );

            $storage->put(
                $path,
                'ok'
            );

            $exists =
                $storage->exists(
                    $path
                );

            $contents =
                $exists
                    ? $storage->get(
                        $path
                    )
                    : null;

            $storage->delete(
                $path
            );

            if (
                ! $exists
                || $contents !== 'ok'
            ) {
                throw new \RuntimeException(
                    'Private storage round-trip verification failed.'
                );
            }

            return [
                'status' => 'ok',
                'latency_ms' =>
                    $this->latency(
                        $started
                    ),
                'disk' => $disk,
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 'fail',
                'latency_ms' =>
                    $this->latency(
                        $started
                    ),
                'disk' => $disk,
                'error' =>
                    $this->message(
                        $exception
                    ),
            ];
        }
    }

    private function schedulerCheck(): array
    {
        $age = $this
            ->heartbeats
            ->ageSeconds(
                'scheduler'
            );

        $stale = (int) config(
            'production_readiness.health.scheduler_stale_seconds',
            180
        );

        if ($age === null) {
            return [
                'status' => 'warning',
                'last_seen_seconds_ago' =>
                    null,
                'stale_after_seconds' =>
                    $stale,
                'message' =>
                    'Scheduler heartbeat has not been recorded.',
            ];
        }

        return [
            'status' =>
                $age <= $stale
                    ? 'ok'
                    : 'warning',
            'last_seen_seconds_ago' =>
                $age,
            'stale_after_seconds' =>
                $stale,
        ];
    }

    private function queueCheck(): array
    {
        $connection =
            (string) config(
                'queue.default'
            );

        $driver =
            (string) config(
                "queue.connections.{$connection}.driver",
                $connection
            );

        if (in_array(
            $driver,
            [
                'sync',
                'deferred',
                'background',
            ],
            true
        )) {
            return [
                'status' => 'ok',
                'connection' =>
                    $connection,
                'driver' => $driver,
                'worker_required' =>
                    false,
                'queues' => [],
            ];
        }

        $required = config(
            'production_readiness.health.required_queues',
            ['default']
        );

        $workerStale = (int) config(
            'production_readiness.health.queue_worker_stale_seconds',
            300
        );

        $queues = [];
        $overall = 'ok';

        foreach ($required as $queueName) {
            $queueName =
                (string) $queueName;

            $age = $this
                ->heartbeats
                ->ageSeconds(
                    'queue-worker:'
                    .$queueName
                );

            $item = [
                'worker_last_seen_seconds_ago' =>
                    $age,
                'worker_stale_after_seconds' =>
                    $workerStale,
                'backlog' => null,
                'oldest_ready_job_seconds' =>
                    null,
                'status' => 'ok',
            ];

            if (
                $age === null
                || $age > $workerStale
            ) {
                $item['status'] =
                    'warning';
            }

            if (
                $driver === 'database'
                && Schema::hasTable(
                    config(
                        "queue.connections.{$connection}.table",
                        'jobs'
                    )
                )
            ) {
                $table = (string) config(
                    "queue.connections.{$connection}.table",
                    'jobs'
                );

                $ready = DB::table(
                    $table
                )
                    ->where(
                        'queue',
                        $queueName
                    )
                    ->whereNull(
                        'reserved_at'
                    )
                    ->where(
                        'available_at',
                        '<=',
                        now()->timestamp
                    );

                $backlog =
                    (clone $ready)->count();

                $oldest =
                    (clone $ready)
                        ->min('created_at');

                $oldestAge =
                    $oldest
                        ? max(
                            0,
                            now()->timestamp
                            - (int) $oldest
                        )
                        : 0;

                $item['backlog'] =
                    $backlog;

                $item[
                    'oldest_ready_job_seconds'
                ] = $oldestAge;

                $backlogWarning =
                    (int) config(
                        'production_readiness.health.queue_backlog_warning',
                        100
                    );

                $backlogCritical =
                    (int) config(
                        'production_readiness.health.queue_backlog_critical',
                        500
                    );

                $oldestWarning =
                    (int) config(
                        'production_readiness.health.queue_oldest_warning_seconds',
                        300
                    );

                $oldestCritical =
                    (int) config(
                        'production_readiness.health.queue_oldest_critical_seconds',
                        900
                    );

                if (
                    $backlog >= $backlogCritical
                    || $oldestAge >= $oldestCritical
                ) {
                    $item['status'] =
                        'critical';
                } elseif (
                    $backlog >= $backlogWarning
                    || $oldestAge >= $oldestWarning
                    || $item['status']
                        !== 'ok'
                ) {
                    $item['status'] =
                        'warning';
                }
            }

            if (
                $item['status']
                === 'critical'
            ) {
                $overall = 'critical';
            } elseif (
                $item['status']
                    === 'warning'
                && $overall === 'ok'
            ) {
                $overall = 'warning';
            }

            $queues[$queueName] =
                $item;
        }

        return [
            'status' => $overall,
            'connection' =>
                $connection,
            'driver' => $driver,
            'worker_required' =>
                true,
            'queues' => $queues,
        ];
    }

    private function failedJobsCheck(): array
    {
        if (! Schema::hasTable(
            'failed_jobs'
        )) {
            return [
                'status' => 'warning',
                'count' => null,
                'message' =>
                    'failed_jobs table is missing.',
            ];
        }

        $count = DB::table(
            'failed_jobs'
        )->count();

        $warning = (int) config(
            'production_readiness.health.failed_jobs_warning',
            1
        );

        return [
            'status' =>
                $count >= $warning
                    ? 'warning'
                    : 'ok',
            'count' => $count,
            'warning_at' =>
                $warning,
        ];
    }

    private function domainCheck(): array
    {
        $checks = [];

        if (Schema::hasTable(
            'wallet_accounting_postings'
        )) {
            $failed =
                WalletAccountingPosting::query()
                    ->where(
                        'status',
                        WalletAccountingPostingStatus::Failed->value
                    )
                    ->count();

            $checks[
                'failed_wallet_accounting_postings'
            ] = $failed;
        }

        if (Schema::hasTable(
            'payment_gateway_events'
        )) {
            $failed =
                PaymentGatewayEvent::query()
                    ->where(
                        'status',
                        PaymentGatewayEventStatus::Failed->value
                    )
                    ->count();

            $checks[
                'failed_payment_gateway_events'
            ] = $failed;
        }

        if (Schema::hasTable(
            'notification_logs'
        )) {
            $failedNotifications =
                NotificationLog::query()
                    ->where(
                        'status',
                        NotificationStatus::Failed->value
                    )
                    ->count();

            $staleMinutes = (int) config(
                'production_readiness.domain.stale_notification_minutes',
                15
            );

            $staleNotifications =
                NotificationLog::query()
                    ->whereIn(
                        'status',
                        [
                            NotificationStatus::Queued->value,
                            NotificationStatus::Processing->value,
                        ]
                    )
                    ->where(
                        'updated_at',
                        '<=',
                        now()->subMinutes($staleMinutes)
                    )
                    ->count();

            $checks['failed_notifications'] =
                $failedNotifications;

            $checks['stale_notifications'] =
                $staleNotifications;
        }

        if (Schema::hasTable(
            'generated_reports'
        )) {
            $minutes = (int) config(
                'production_readiness.domain.stale_generated_report_minutes',
                30
            );

            $stale =
                GeneratedReport::query()
                    ->where(
                        'status',
                        ReportStatus::Processing->value
                    )
                    ->where(
                        'started_at',
                        '<=',
                        now()->subMinutes(
                            $minutes
                        )
                    )
                    ->count();

            $checks[
                'stale_generated_reports'
            ] = $stale;
        }

        $accountingWarning =
            (int) config(
                'production_readiness.domain.failed_accounting_postings_warning',
                1
            );

        $gatewayWarning =
            (int) config(
                'production_readiness.domain.failed_gateway_events_warning',
                1
            );

        $notificationWarning =
            (int) config(
                'production_readiness.domain.failed_notifications_warning',
                1
            );

        $warning =
            (
                $checks[
                    'failed_wallet_accounting_postings'
                ] ?? 0
            ) >= $accountingWarning
            || (
                $checks[
                    'failed_payment_gateway_events'
                ] ?? 0
            ) >= $gatewayWarning
            || (
                $checks[
                    'stale_generated_reports'
                ] ?? 0
            ) > 0
            || (
                $checks[
                    'failed_notifications'
                ] ?? 0
            ) >= $notificationWarning
            || (
                $checks[
                    'stale_notifications'
                ] ?? 0
            ) > 0;

        return [
            'status' =>
                $warning
                    ? 'warning'
                    : 'ok',
            ...$checks,
        ];
    }

    private function latency(
        float $started
    ): float {
        return round(
            (
                microtime(true)
                - $started
            ) * 1000,
            2
        );
    }

    private function message(
        \Throwable $exception
    ): string {
        return mb_substr(
            $exception->getMessage(),
            0,
            1000
        );
    }
}
