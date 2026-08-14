<?php

return [
    'disk' => env('REPORT_EXPORT_DISK', 'local'),

    'directory' => env(
        'REPORT_EXPORT_DIRECTORY',
        'generated-reports'
    ),

    'queue' => env(
        'REPORT_EXPORT_QUEUE',
        'reports'
    ),

    'retention_days' => (int) env(
        'REPORT_EXPORT_RETENTION_DAYS',
        7
    ),

    'max_rows' => (int) env(
        'REPORT_EXPORT_MAX_ROWS',
        50000
    ),
];
