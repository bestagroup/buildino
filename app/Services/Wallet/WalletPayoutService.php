<?php

namespace App\Services\Wallet;

use App\Enums\WalletPayoutStatus;
use App\Enums\WalletTransferType;
use App\Models\Building;
use App\Models\BuildingBankAccount;
use App\Models\User;
use App\Models\WalletPayoutRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WalletPayoutService
{
    public function __construct(
        private readonly WalletService $wallets
    ) {
    }

    public function request(
        Building $building,
        BuildingBankAccount $bankAccount,
        User $actor,
        int $amount
    ): WalletPayoutRequest {
        if (
            (int) $bankAccount->building_id !== (int) $building->getKey()
            || ! $bankAccount->is_active
            || ! $bankAccount->is_verified
        ) {
            throw ValidationException::withMessages([
                'building_bank_account_id' => 'A verified active bank account of this building is required.',
            ]);
        }

        $wallet = $this->wallets->walletFor($building);

        $this->wallets->lockFunds($wallet, $amount);

        try {
            return WalletPayoutRequest::query()->create([
                'uuid' => (string) Str::uuid(),
                'building_id' => $building->getKey(),
                'wallet_id' => $wallet->getKey(),
                'building_bank_account_id' => $bankAccount->getKey(),
                'amount' => $amount,
                'fee_amount' => 0,
                'net_amount' => $amount,
                'status' => WalletPayoutStatus::Pending,
                'requested_by' => $actor->getKey(),
            ]);
        } catch (\Throwable $e) {
            $this->wallets->unlockFunds($wallet, $amount);
            throw $e;
        }
    }

    public function approve(
        WalletPayoutRequest $request,
        User $actor
    ): WalletPayoutRequest {
        return DB::transaction(function () use ($request, $actor): WalletPayoutRequest {
            $request = WalletPayoutRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($request->status !== WalletPayoutStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending payout requests can be approved.',
                ]);
            }

            $request->update([
                'status' => WalletPayoutStatus::Approved,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ]);

            return $request->refresh();
        });
    }

    public function reject(
        WalletPayoutRequest $request,
        User $actor,
        ?string $reason = null
    ): WalletPayoutRequest {
        $request->refresh();

        if (! in_array(
            $request->status,
            [WalletPayoutStatus::Pending, WalletPayoutStatus::Approved],
            true
        )) {
            throw ValidationException::withMessages([
                'status' => 'Payout request cannot be rejected in its current status.',
            ]);
        }

        $this->wallets->unlockFunds(
            $request->wallet,
            (int) $request->amount
        );

        $request->update([
            'status' => WalletPayoutStatus::Rejected,
            'approved_by' => $request->approved_by ?: $actor->getKey(),
            'rejection_reason' => $reason,
            'rejected_at' => now(),
        ]);

        return $request->refresh();
    }

    public function markPaid(
        WalletPayoutRequest $request,
        User $actor,
        string $bankReference
    ): WalletPayoutRequest {
        $request->refresh();

        if ($request->status === WalletPayoutStatus::Paid) {
            return $request;
        }

        if ($request->status !== WalletPayoutStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'Only approved payout requests can be marked as paid.',
            ]);
        }

        $transfer = $this->wallets->debitLocked(
            $request->wallet,
            (int) $request->amount,
            WalletTransferType::Payout,
            'wallet-payout:'.$request->getKey().':paid',
            $request,
            $actor,
            'Building wallet payout'
        );

        $request->update([
            'status' => WalletPayoutStatus::Paid,
            'wallet_transfer_id' => $transfer->getKey(),
            'paid_by' => $actor->getKey(),
            'bank_reference' => $bankReference,
            'paid_at' => now(),
        ]);

        return $request->refresh();
    }
}
