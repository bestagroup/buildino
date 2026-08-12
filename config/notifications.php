<?php

return [
    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
    'sms_provider' => env('SMS_PROVIDER', 'log'),
    'push_provider' => env('PUSH_PROVIDER', 'log'),
];
