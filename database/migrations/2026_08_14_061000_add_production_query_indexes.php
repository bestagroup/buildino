<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('wallet_transfers')
            && ! Schema::hasIndex(
                'wallet_transfers',
                'wt_source_status_completed_idx'
            )
        ) {
            Schema::table(
                'wallet_transfers',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'source_wallet_id',
                            'status',
                            'completed_at',
                        ],
                        'wt_source_status_completed_idx'
                    );
                }
            );
        }

        if (
            Schema::hasTable('wallet_transfers')
            && ! Schema::hasIndex(
                'wallet_transfers',
                'wt_dest_status_completed_idx'
            )
        ) {
            Schema::table(
                'wallet_transfers',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'destination_wallet_id',
                            'status',
                            'completed_at',
                        ],
                        'wt_dest_status_completed_idx'
                    );
                }
            );
        }

        if (
            Schema::hasTable('unit_invoices')
            && ! Schema::hasIndex(
                'unit_invoices',
                'ui_building_status_due_idx'
            )
        ) {
            Schema::table(
                'unit_invoices',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'building_id',
                            'status',
                            'due_date',
                        ],
                        'ui_building_status_due_idx'
                    );
                }
            );
        }

        if (
            Schema::hasTable('service_request_wallet_payments')
            && ! Schema::hasIndex(
                'service_request_wallet_payments',
                'srwp_status_settled_idx'
            )
        ) {
            Schema::table(
                'service_request_wallet_payments',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'status',
                            'settled_at',
                        ],
                        'srwp_status_settled_idx'
                    );
                }
            );
        }

        if (
            Schema::hasTable('provider_payout_requests')
            && ! Schema::hasIndex(
                'provider_payout_requests',
                'ppr_status_paid_idx'
            )
        ) {
            Schema::table(
                'provider_payout_requests',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'status',
                            'paid_at',
                        ],
                        'ppr_status_paid_idx'
                    );
                }
            );
        }

        if (
            Schema::hasTable('payment_gateway_events')
            && ! Schema::hasIndex(
                'payment_gateway_events',
                'pge_status_received_idx'
            )
        ) {
            Schema::table(
                'payment_gateway_events',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'status',
                            'received_at',
                        ],
                        'pge_status_received_idx'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        $indexes = [
            'wallet_transfers' => [
                'wt_source_status_completed_idx',
                'wt_dest_status_completed_idx',
            ],
            'unit_invoices' => [
                'ui_building_status_due_idx',
            ],
            'service_request_wallet_payments' => [
                'srwp_status_settled_idx',
            ],
            'provider_payout_requests' => [
                'ppr_status_paid_idx',
            ],
            'payment_gateway_events' => [
                'pge_status_received_idx',
            ],
        ];

        foreach ($indexes as $tableName => $names) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($names as $name) {
                if (! Schema::hasIndex($tableName, $name)) {
                    continue;
                }

                Schema::table(
                    $tableName,
                    function (Blueprint $table) use ($name): void {
                        $table->dropIndex($name);
                    }
                );
            }
        }
    }
};
