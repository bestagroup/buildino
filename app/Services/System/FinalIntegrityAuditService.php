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

        if (
            Schema::hasTable('invoice_installments')
            && Schema::hasTable('unit_invoices')
        ) {
            $checks[] = [
                'name' => 'installment_plan_total_mismatch',
                'severity' => 'critical',
                'count' => DB::table('unit_invoices as ui')
                    ->join('invoice_installments as ii', 'ii.unit_invoice_id', '=', 'ui.id')
                    ->select('ui.id')
                    ->where('ii.status', '!=', 'cancelled')
                    ->groupBy('ui.id', 'ui.total_amount')
                    ->havingRaw(
                        'SUM(ii.amount + ii.penalty_amount - ii.waived_amount) <> ui.total_amount'
                    )
                    ->get()
                    ->count(),
            ];

            $checks[] = [
                'name' => 'installment_paid_total_mismatch',
                'severity' => 'critical',
                'count' => DB::table('unit_invoices as ui')
                    ->join('invoice_installments as ii', 'ii.unit_invoice_id', '=', 'ui.id')
                    ->select('ui.id')
                    ->where('ii.status', '!=', 'cancelled')
                    ->groupBy('ui.id', 'ui.paid_amount')
                    ->havingRaw('SUM(ii.paid_amount) <> ui.paid_amount')
                    ->get()
                    ->count(),
            ];
        }

        if (
            Schema::hasTable('loyalty_accounts')
            && Schema::hasTable('loyalty_transactions')
            && Schema::hasColumn('loyalty_transactions', 'balance_after')
        ) {
            $checks[] = [
                'name' => 'loyalty_account_balance_mismatch',
                'severity' => 'critical',
                'count' => DB::table('loyalty_accounts as la')
                    ->leftJoin('loyalty_transactions as lt', 'lt.loyalty_account_id', '=', 'la.id')
                    ->select('la.id')
                    ->groupBy('la.id', 'la.balance')
                    ->havingRaw('COALESCE(SUM(lt.points), 0) <> la.balance')
                    ->get()
                    ->count(),
            ];

            $checks[] = [
                'name' => 'loyalty_account_negative_balance',
                'severity' => 'critical',
                'count' => DB::table('loyalty_accounts')
                    ->where('balance', '<', 0)
                    ->count(),
            ];

            $checks[] = [
                'name' => 'loyalty_remaining_points_invalid',
                'severity' => 'critical',
                'count' => DB::table('loyalty_transactions')
                    ->whereNotNull('remaining_points')
                    ->where(function ($query): void {
                        $query
                            ->where('points', '<=', 0)
                            ->orWhereColumn('remaining_points', '>', 'points');
                    })
                    ->count(),
            ];
        }

        if (
            Schema::hasTable('loyalty_reward_claims')
            && Schema::hasColumn('loyalty_reward_claims', 'loyalty_transaction_id')
        ) {
            $checks[] = [
                'name' => 'funded_loyalty_claim_missing_transaction',
                'severity' => 'critical',
                'count' => DB::table('loyalty_reward_claims')
                    ->whereIn('status', ['pending', 'approved'])
                    ->whereNull('loyalty_transaction_id')
                    ->count(),
            ];
        }

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
