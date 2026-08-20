<?php

return [
    'version' => '1.0.0',

    /*
     * Routes without auth:sanctum are treated as a security boundary.
     * Any unexpected public V1 route makes api:contract:audit fail.
     */
    'allowed_public_routes' => [
        'POST api/v1/auth/otp/request',
        'POST api/v1/auth/otp/login',
        'POST api/v1/auth/password/login',

        'GET api/v1/payment-gateways/{gateway}/callback',
        'POST api/v1/payment-gateways/{gateway}/callback',
        'POST api/v1/payment-gateways/{gateway}/webhook',

        'GET api/v1/system/readiness',
    ],

    /*
     * Release-critical contracts. Audit validates existence plus
     * public/protected classification.
     */
    'critical_routes' => [
        [
            'method' => 'POST',
            'uri' => 'api/v1/auth/password/login',
            'protected' => false,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/auth/me',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/buildings',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/units/{unit}',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/wallets/me',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/buildings/{building}/wallet-topups',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/invoices/{unitInvoice}/payments',
            'protected' => true,
        ],
        [
            'method' => 'PUT',
            'uri' => 'api/v1/invoices/{unitInvoice}/installments',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/invoices/{unitInvoice}/penalty-adjustments',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/payments/{payment}/receipt',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/loyalty/me',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/loyalty/rewards/{loyaltyReward}/claims',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/payments/{payment}/gateway/initiate',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/payment-gateways/{gateway}/webhook',
            'protected' => false,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/facilities/{buildingFacility}/reservations',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/facility-reservations/{facilityReservation}/pay',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/service-requests/{serviceRequest}/quotes',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/service-request-quotes/{serviceRequestQuote}/accept',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/buildings/{building}/dashboard/management',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/report-definitions/{reportDefinition}/exports',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/system/readiness',
            'protected' => false,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/admin/system/health',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/service-requests/{serviceRequest}/assign',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/support-tickets/{supportTicket}/messages',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/notifications',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/app/bootstrap',
            'protected' => true,
        ],
        [
            'method' => 'POST',
            'uri' => 'api/v1/documents/{document}/files',
            'protected' => true,
        ],
        [
            'method' => 'GET',
            'uri' => 'api/v1/files/{file}/download',
            'protected' => true,
        ],
    ],

    /*
     * Request examples are shared by OpenAPI and Postman generation.
     * They are examples, not a second source of validation truth.
     * Laravel FormRequest remains authoritative.
     */
    'request_examples' => [
        'POST api/v1/auth/otp/request' => [
            'identifier' => '{{mobile}}',
            'channel' => 'sms',
        ],

        'POST api/v1/auth/otp/login' => [
            'identifier' => '{{mobile}}',
            'channel' => 'sms',
            'code' => '{{otp_code}}',
            'device_name' => 'Postman',
        ],

        'POST api/v1/auth/password/login' => [
            'login' => '{{mobile}}',
            'password' => '{{password}}',
            'device_name' => 'Postman',
        ],

        'POST api/v1/buildings/{building}/wallet-topups' => [
            'target_type' => 'user_wallet',
            'amount' => 500000,
            'method' => 'online',
            'gateway' => '{{gateway}}',
            'idempotency_key' => '{{payment_idempotency_key}}',
            'description' => 'Postman RC wallet top-up',
        ],

        'POST api/v1/invoices/{unitInvoice}/payments' => [
            'amount' => 100000,
            'method' => 'online',
            'idempotency_key' => '{{invoice_payment_idempotency_key}}',
            'description' => 'Postman invoice payment',
        ],

        'PUT api/v1/invoices/{unitInvoice}/installments' => [
            'installments' => [
                [
                    'due_date' => '2026-09-20',
                    'amount' => 500000,
                ],
                [
                    'due_date' => '2026-10-20',
                    'amount' => 500000,
                ],
            ],
        ],

        'POST api/v1/invoices/{unitInvoice}/penalty-adjustments' => [
            'action' => 'add',
            'amount' => 50000,
            'reason' => 'Late payment penalty',
        ],

        'POST api/v1/loyalty/rewards/{loyaltyReward}/claims' => [
            'idempotency_key' => '{{loyalty_claim_idempotency_key}}',
        ],

        'POST api/v1/buildings/{building}/loyalty-rules' => [
            'event_type' => 'payment_verified',
            'points' => 1,
            'configuration' => [
                'amount_step' => 100000,
                'maximum_points' => 500,
                'expires_days' => 365,
            ],
            'is_active' => true,
        ],

        'POST api/v1/buildings/{building}/loyalty-rewards' => [
            'title' => 'Service discount',
            'required_points' => 100,
            'is_active' => true,
        ],

        'POST api/v1/loyalty-claims/{loyaltyRewardClaim}/reject' => [
            'reason' => 'Reward is temporarily unavailable.',
        ],

        'POST api/v1/payments/{payment}/gateway/initiate' => [
            'gateway' => '{{gateway}}',
            'idempotency_key' => '{{payment_idempotency_key}}',
        ],

        'POST api/v1/facilities/{buildingFacility}/reservations' => [
            'unit_id' => '{{unit_id}}',
            'reservation_date' => '2026-08-20',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'description' => 'Postman RC reservation',
        ],

        'POST api/v1/facility-reservations/{facilityReservation}/pay' => [
            'payer_source' => 'user_wallet',
        ],

        'POST api/v1/service-requests/{serviceRequest}/quotes' => [
            'amount' => 500000,
            'notes' => 'Postman RC quote',
        ],

        'POST api/v1/service-request-quotes/{serviceRequestQuote}/accept' => [
            'payer_source' => 'user_wallet',
        ],

        'PUT api/v1/buildings/{building}/service-financial-setting' => [
            'platform_commission_bps' => 1000,
            'allow_user_wallet' => true,
            'allow_unit_wallet' => true,
            'is_active' => true,
        ],

        'POST api/v1/provider/bank-accounts' => [
            'bank_name' => 'Test Bank',
            'account_holder_name' => 'Provider',
            'iban' => 'IR000000000000000000000001',
            'is_default' => true,
        ],

        'POST api/v1/provider/payouts' => [
            'provider_bank_account_id' => '{{provider_bank_account_id}}',
            'amount' => 100000,
            'currency' => 'IRR',
        ],

        'POST api/v1/service-requests/{serviceRequest}/assign' => [
            'assigned_to' => '{{provider_user_id}}',
        ],

        'POST api/v1/support-tickets/{supportTicket}/messages' => [
            'message' => 'Postman support reply',
            'is_internal' => false,
        ],

        'POST api/v1/notification-devices' => [
            'device_id' => 'postman-device',
            'platform' => 'android',
            'device_name' => 'Postman Device',
            'push_token' => '{{push_token}}',
        ],

        'PUT api/v1/notification-preferences' => [
            'preferences' => [
                [
                    'notification_type' => 'support.message',
                    'channel' => 'push',
                    'is_enabled' => true,
                ],
            ],
        ],

        'POST api/v1/report-definitions/{reportDefinition}/exports' => [
            'building_id' => '{{building_id}}',
            'format' => 'csv',
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'granularity' => 'day',
        ],
    ],

    /*
     * Converts Laravel route model parameter names into practical Postman
     * environment variables.
     */
    'postman_parameter_variables' => [
        'building' => 'building_id',
        'block' => 'block_id',
        'floor' => 'floor_id',
        'unit' => 'unit_id',
        'unitOwnership' => 'unit_ownership_id',
        'unitOccupancy' => 'unit_occupancy_id',
        'unitInvitation' => 'unit_invitation_id',
        'guestVisit' => 'guest_visit_id',
        'buildingFacility' => 'facility_id',
        'facilitySchedule' => 'facility_schedule_id',
        'facilityTimeSlot' => 'facility_time_slot_id',
        'facilityBlackout' => 'facility_blackout_id',
        'facilityReservation' => 'facility_reservation_id',
        'chargeFormula' => 'charge_formula_id',
        'chargePeriod' => 'charge_period_id',
        'unitInvoice' => 'invoice_id',
        'payment' => 'payment_id',
        'loyaltyReward' => 'loyalty_reward_id',
        'loyaltyRewardClaim' => 'loyalty_claim_id',
        'expense' => 'expense_id',
        'income' => 'income_id',
        'announcement' => 'announcement_id',
        'serviceRequest' => 'service_request_id',
        'serviceRequestQuote' => 'service_quote_id',
        'document' => 'document_id',
        'meetingMinute' => 'meeting_minute_id',
        'file' => 'file_uuid',
        'supportTicket' => 'support_ticket_id',
        'wallet' => 'wallet_id',
        'walletTopUp' => 'wallet_topup_id',
        'buildingBankAccount' => 'building_bank_account_id',
        'walletPayoutRequest' => 'wallet_payout_id',
        'buildingBillPayment' => 'building_bill_payment_id',
        'providerBankAccount' => 'provider_bank_account_id',
        'providerPayoutRequest' => 'provider_payout_id',
        'walletTransfer' => 'wallet_transfer_id',
        'reportDefinition' => 'report_definition_id',
        'generatedReport' => 'generated_report_id',
        'gateway' => 'gateway',
        'userDevice' => 'notification_device_id',
        'complex' => 'complex_id',
    ],
];
