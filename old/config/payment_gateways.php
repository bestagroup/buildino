<?php

return [
    'default' => env(
        'PAYMENT_GATEWAY_DEFAULT',
        'generic'
    ),

    'callback_base_url' => env(
        'PAYMENT_GATEWAY_CALLBACK_BASE_URL',
        env('APP_URL')
    ),

    'webhook_max_skew_seconds' => (int) env(
        'PAYMENT_GATEWAY_WEBHOOK_MAX_SKEW',
        300
    ),

    'gateways' => [
        /*
         * Provider-neutral HTTPS JSON adapter.
         *
         * Map these fields to the actual PSP contract in production.
         * No gateway is enabled by default.
         */
        'generic' => [
            'driver' => 'generic_hmac_json',
            'enabled' => env(
                'PAYMENT_GATEWAY_GENERIC_ENABLED',
                false
            ),

            'request_url' => env(
                'PAYMENT_GATEWAY_GENERIC_REQUEST_URL'
            ),

            'verify_url' => env(
                'PAYMENT_GATEWAY_GENERIC_VERIFY_URL'
            ),

            'merchant_id' => env(
                'PAYMENT_GATEWAY_GENERIC_MERCHANT_ID'
            ),

            'secret' => env(
                'PAYMENT_GATEWAY_GENERIC_SECRET'
            ),

            'webhook_secret' => env(
                'PAYMENT_GATEWAY_GENERIC_WEBHOOK_SECRET'
            ),

            'timeout_seconds' => (int) env(
                'PAYMENT_GATEWAY_GENERIC_TIMEOUT',
                15
            ),

            'request_signature_enabled' => (bool) env(
                'PAYMENT_GATEWAY_GENERIC_SIGN_REQUESTS',
                true
            ),

            'request_signature_header' =>
                'X-Signature',

            'request_timestamp_header' =>
                'X-Timestamp',

            'webhook_signature_header' =>
                'X-Signature',

            'webhook_timestamp_header' =>
                'X-Timestamp',

            'webhook_event_id_header' =>
                'X-Event-Id',

            'authority_request_field' =>
                'authority',

            'authority_response_path' =>
                'data.authority',

            'redirect_url_response_path' =>
                'data.redirect_url',

            'redirect_url_template' => env(
                'PAYMENT_GATEWAY_GENERIC_REDIRECT_TEMPLATE'
            ),

            'gateway_transaction_id_path' =>
                'data.transaction_id',

            'verify_success_path' =>
                'data.success',

            'verify_success_values' => [
                true,
                1,
                '1',
                'true',
                'success',
                'paid',
            ],

            'verify_amount_path' =>
                'data.amount',

            'verify_currency_path' =>
                'data.currency',

            'verify_gateway_transaction_id_path' =>
                'data.transaction_id',

            'verify_tracking_code_path' =>
                'data.tracking_code',

            'verify_reference_number_path' =>
                'data.reference_number',

            'verify_merchant_reference_path' =>
                'data.order_id',

            'verify_error_code_path' =>
                'error.code',

            'verify_error_message_path' =>
                'error.message',

            'callback_authority_fields' => [
                'authority',
                'Authority',
                'token',
            ],

            'webhook_event_id_fields' => [
                'event_id',
                'id',
            ],
        ],

        /*
         * Test-only driver. Tests enable it explicitly with config().
         */
        'fake' => [
            'driver' => 'fake',
            'enabled' => false,
            'webhook_secret' =>
                'buildino-test-secret',
        ],
    ],
];
