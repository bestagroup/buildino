<?php

return [
    'health' => [
        'scheduler_stale_seconds' => (int) env(
            'HEALTH_SCHEDULER_STALE_SECONDS',
            180
        ),

        'queue_worker_stale_seconds' => (int) env(
            'HEALTH_QUEUE_WORKER_STALE_SECONDS',
            300
        ),

        'queue_backlog_warning' => (int) env(
            'HEALTH_QUEUE_BACKLOG_WARNING',
            100
        ),

        'queue_backlog_critical' => (int) env(
            'HEALTH_QUEUE_BACKLOG_CRITICAL',
            500
        ),

        'queue_oldest_warning_seconds' => (int) env(
            'HEALTH_QUEUE_OLDEST_WARNING_SECONDS',
            300
        ),

        'queue_oldest_critical_seconds' => (int) env(
            'HEALTH_QUEUE_OLDEST_CRITICAL_SECONDS',
            900
        ),

        'failed_jobs_warning' => (int) env(
            'HEALTH_FAILED_JOBS_WARNING',
            1
        ),

        'required_queues' => array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        (string) env(
                            'HEALTH_REQUIRED_QUEUES',
                            'default,reports,notifications'
                        )
                    )
                )
            )
        ),

        'storage_disk' => env(
            'HEALTH_STORAGE_DISK',
            'local'
        ),
    ],

    'domain' => [
        'failed_accounting_postings_warning' => (int) env(
            'HEALTH_FAILED_ACCOUNTING_WARNING',
            1
        ),

        'failed_gateway_events_warning' => (int) env(
            'HEALTH_FAILED_GATEWAY_WARNING',
            1
        ),

        'stale_generated_report_minutes' => (int) env(
            'HEALTH_STALE_REPORT_MINUTES',
            30
        ),

        'failed_notifications_warning' => (int) env(
            'HEALTH_FAILED_NOTIFICATIONS_WARNING',
            1
        ),

        'stale_notification_minutes' => (int) env(
            'HEALTH_STALE_NOTIFICATION_MINUTES',
            15
        ),
    ],

    'security' => [
        'hsts_max_age' => (int) env(
            'SECURITY_HSTS_MAX_AGE',
            31536000
        ),

        'hsts_include_subdomains' => (bool) env(
            'SECURITY_HSTS_INCLUDE_SUBDOMAINS',
            true
        ),
    ],
];
