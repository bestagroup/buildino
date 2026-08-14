<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FinalIntegrityAuditService
{
    public function inspect(): array
    {
        $checks = [];

        $this->check(
            $checks,
            'wallet_locked_exceeds_balance',
            'critical',
            'wallets',
            fn () => DB::table('wallets')
                ->whereColumn('locked_balance', '>', 'balance')
                ->count()
        );

        $this->check(
            $checks,
            'completed_transfer_without_completed_at',
            'critical',
            'wallet_transfers',
            fn () => DB::table('wallet_transfers')
                ->where('status', 'completed')
                ->whereNull('completed_at')
                ->count()
        );

        $this->check(
            $checks,
            'paid_payment_without_verified_at',
            'critical',
            'payments',
            fn () => DB::table('payments')
                ->where('status', 'paid')
                ->whereNull('verified_at')
                ->count()
        );

        $this->check(
            $checks,
            'credited_topup_without_transfer',
            'critical',
            'wallet_topups',
            fn () => DB::table('wallet_topups')
                ->where('status', 'credited')
                ->where(function ($query): void {
                    $query
                        ->whereNull('wallet_transfer_id')
                        ->orWhereNull('credited_at');
                })
                ->count()
        );

        if (
            Schema::hasTable('wallet_topups')
            && Schema::hasTable('payments')
        ) {
            $checks[] = [
                'name' => 'credited_topup_with_unpaid_payment',
                'severity' => 'critical',
                'count' => DB::table('wallet_topups as wt')
                    ->join('payments as p', 'p.id', '=', 'wt.payment_id')
                    ->where('wt.status', 'credited')
                    ->where('p.status', '!=', 'paid')
                    ->count(),
            ];
        }

        $this->check(
            $checks,
            'settled_service_payment_missing_transfers',
            'critical',
            'service_request_wallet_payments',
            fn () => DB::table('service_request_wallet_payments')
                ->where('status', 'settled')
                ->where(function ($query): void {
                    $query
                        ->where(function ($provider): void {
                            $provider
                                ->where('provider_amount', '>', 0)
                                ->whereNull('provider_transfer_id');
                        })
                        ->orWhere(function ($commission): void {
                            $commission
                                ->where('commission_amount', '>', 0)
                                ->whereNull('commission_transfer_id');
                        });
                })
                ->count()
        );

        $this->check(
            $checks,
            'paid_provider_payout_missing_transfer_or_reference',
            'critical',
            'provider_payout_requests',
            fn () => DB::table('provider_payout_requests')
                ->where('status', 'paid')
                ->where(function ($query): void {
                    $query
                        ->whereNull('wallet_transfer_id')
                        ->orWhereNull('bank_reference')
                        ->orWhereNull('paid_at');
                })
                ->count()
        );

        $this->check(
            $checks,
            'posted_accounting_without_transaction',
            'critical',
            'wallet_accounting_postings',
            fn () => DB::table('wallet_accounting_postings')
                ->where('status', 'posted')
                ->whereNull('financial_transaction_id')
                ->count()
        );

        $this->check(
            $checks,
            'stale_notification_delivery',
            'warning',
            'notification_logs',
            fn () => DB::table('notification_logs')
                ->whereIn('status', ['queued', 'processing'])
                ->where('updated_at', '<=', now()->subMinutes(15))
                ->count()
        );

        $this->check(
            $checks,
            'overdue_support_first_response',
            'warning',
            'support_tickets',
            fn () => DB::table('support_tickets')
                ->whereNull('first_response_at')
                ->whereNotNull('response_due_at')
                ->where('response_due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count()
        );

        $this->check(
            $checks,
            'overdue_support_resolution',
            'warning',
            'support_tickets',
            fn () => DB::table('support_tickets')
                ->whereNotNull('resolution_due_at')
                ->where('resolution_due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count()
        );

        $critical = collect($checks)
            ->where('severity', 'critical')
            ->sum('count');

        $warnings = collect($checks)
            ->where('severity', 'warning')
            ->sum('count');

        return [
            'ok' => $critical === 0,
            'critical_count' => (int) $critical,
            'warning_count' => (int) $warnings,
            'checks' => $checks,
            'generated_at' => now()->toISOString(),
        ];
    }

    private function check(
        array &$checks,
        string $name,
        string $severity,
        string $table,
        callable $count
    ): void {
        if (! Schema::hasTable($table)) {
            $checks[] = [
                'name' => $name,
                'severity' => $severity,
                'count' => 0,
                'skipped' => true,
                'reason' => "Table [{$table}] does not exist.",
            ];

            return;
        }

        $checks[] = [
            'name' => $name,
            'severity' => $severity,
            'count' => (int) $count(),
        ];
    }
}
