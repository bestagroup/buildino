<?php

namespace App\Services\Wallet;

use App\Enums\BuildingBillPaymentStatus;
use App\Enums\ServiceRequestWalletPaymentStatus;
use App\Enums\WalletEntryType;
use App\Enums\WalletPayoutStatus;
use App\Enums\WalletReconciliationStatus;
use App\Models\BuildingBillPayment;
use App\Models\ProviderPayoutRequest;
use App\Models\ServiceRequestWalletPayment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Models\WalletPayoutRequest;
use App\Models\WalletReconciliation;
use Illuminate\Support\Str;

final class WalletReconciliationService
{
    public function reconcile(
        Wallet $wallet,
        ?User $actor = null
    ): WalletReconciliation {
        $wallet->refresh();

        $credit = (int) WalletEntry::query()
            ->where('wallet_id', $wallet->getKey())
            ->where(
                'entry_type',
                WalletEntryType::Credit->value
            )
            ->sum('amount');

        $debit = (int) WalletEntry::query()
            ->where('wallet_id', $wallet->getKey())
            ->where(
                'entry_type',
                WalletEntryType::Debit->value
            )
            ->sum('amount');

        $entryBalance = $credit - $debit;

        $buildingPayoutLocks =
            (int) WalletPayoutRequest::query()
                ->where('wallet_id', $wallet->getKey())
                ->whereIn(
                    'status',
                    [
                        WalletPayoutStatus::Pending->value,
                        WalletPayoutStatus::Approved->value,
                    ]
                )
                ->sum('amount');

        $billPaymentLocks =
            (int) BuildingBillPayment::query()
                ->where('wallet_id', $wallet->getKey())
                ->where(
                    'status',
                    BuildingBillPaymentStatus::Pending->value
                )
                ->sum('amount');

        $servicePaymentLocks =
            (int) ServiceRequestWalletPayment::query()
                ->where(
                    'source_wallet_id',
                    $wallet->getKey()
                )
                ->where(
                    'status',
                    ServiceRequestWalletPaymentStatus::Locked->value
                )
                ->sum('amount');

        $providerPayoutLocks =
            (int) ProviderPayoutRequest::query()
                ->where('wallet_id', $wallet->getKey())
                ->whereIn(
                    'status',
                    [
                        WalletPayoutStatus::Pending->value,
                        WalletPayoutStatus::Approved->value,
                    ]
                )
                ->sum('amount');

        $expectedLocked =
            $buildingPayoutLocks
            + $billPaymentLocks
            + $servicePaymentLocks
            + $providerPayoutLocks;

        $storedBalance = (int) $wallet->balance;
        $storedLocked = (int) $wallet->locked_balance;

        $balanceDifference =
            $storedBalance - $entryBalance;

        $lockDifference =
            $storedLocked - $expectedLocked;

        $latestEntry = WalletEntry::query()
            ->where('wallet_id', $wallet->getKey())
            ->latest('id')
            ->first();

        $latestEntryBalanceMatches =
            $latestEntry === null
                ? $storedBalance === 0
                : (int) $latestEntry->balance_after
                    === $storedBalance;

        $locksWithinBalance =
            $storedLocked <= $storedBalance;

        $matched =
            $balanceDifference === 0
            && $lockDifference === 0
            && $latestEntryBalanceMatches
            && $locksWithinBalance;

        return WalletReconciliation::query()->create([
            'uuid' => (string) Str::uuid(),
            'wallet_id' => $wallet->getKey(),
            'reconciled_at' => now(),
            'entry_balance' => $entryBalance,
            'stored_balance' => $storedBalance,
            'expected_locked_balance' => $expectedLocked,
            'stored_locked_balance' => $storedLocked,
            'balance_difference' => $balanceDifference,
            'lock_difference' => $lockDifference,
            'status' => $matched
                ? WalletReconciliationStatus::Matched
                : WalletReconciliationStatus::Mismatch,
            'details' => [
                'credits' => $credit,
                'debits' => $debit,
                'latest_entry_id' =>
                    $latestEntry?->getKey(),
                'latest_entry_balance_after' =>
                    $latestEntry
                        ? (int) $latestEntry->balance_after
                        : null,
                'latest_entry_balance_matches' =>
                    $latestEntryBalanceMatches,
                'locks_within_balance' =>
                    $locksWithinBalance,
                'lock_components' => [
                    'building_payouts' =>
                        $buildingPayoutLocks,
                    'building_bills' =>
                        $billPaymentLocks,
                    'service_payments' =>
                        $servicePaymentLocks,
                    'provider_payouts' =>
                        $providerPayoutLocks,
                ],
            ],
            'created_by' =>
                $actor?->getKey(),
        ])->refresh();
    }
}
