<?php

return [
    'disk' => env('FILE_DISK', 'private'),

    'allowed_disks' => [
        'private',
    ],

    'max_upload_kilobytes' => (int) env(
        'FILE_MAX_UPLOAD_KILOBYTES',
        51200
    ),

    'allowed_extensions' => [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'txt',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'zip',
    ],

    'categories' => [
        'building',
        'unit',
        'contract',
        'ownership',
        'lease',
        'meeting_minute',
        'financial',
        'support',
        'other',
    ],

    'scan' => [
        'enabled' => (bool) env('FILE_SCAN_ENABLED', false),
        'binary' => env('FILE_SCAN_BINARY', 'clamdscan'),
        'timeout_seconds' => (int) env(
            'FILE_SCAN_TIMEOUT_SECONDS',
            30
        ),
    ],
];
