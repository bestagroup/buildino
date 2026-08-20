<?php

namespace App\Services\Wallet;

use App\Enums\WalletPayoutStatus;
use App\Enums\WalletTransferType;
use App\Models\Building;
use App\Models\BuildingBankAccount;
use App\Models\User;
use App\Models\WalletPayoutRequest;
use Illuminate\Database\UniqueConstraintViolationException;
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
        int $amount,
        ?string $idempotencyKey = null
    ): WalletPayoutRequest {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payout amount must be greater than zero.',
            ]);
        }

        $idempotencyKey = $this->normalizeIdempotencyKey(
            $idempotencyKey
        );

        try {
            return DB::transaction(function () use (
                $building,
                $bankAccount,
                $actor,
                $amount,
                $idempotencyKey
            ): WalletPayoutRequest {
                $existing = $this->existingRequest(
                    $actor,
                    $idempotencyKey
                );

                if ($existing) {
                    $this->assertSameRequest(
                        $existing,
                        $building,
                        $bankAccount,
                        $actor,
                        $amount
                    );

                    return $existing;
                }

                $bankAccount = BuildingBankAccount::query()
                    ->lockForUpdate()
                    ->findOrFail($bankAccount->getKey());

                $this->assertBankAccount($building, $bankAccount);

                $existing = $this->existingRequest(
                    $actor,
                    $idempotencyKey
                );

                if ($existing) {
                    $this->assertSameRequest(
                        $existing,
                        $building,
                        $bankAccount,
                        $actor,
                        $amount
                    );

                    return $existing;
                }

                $wallet = $this->wallets->walletFor($building);

                /*
                 * The outer transaction intentionally wraps both the wallet
                 * lock and payout creation. If persistence fails, the locked
                 * balance is rolled back together with the request.
                 */
                $this->wallets->lockFunds($wallet, $amount);

                /*
                 * A concurrent retry may have completed while this request
                 * waited for the wallet row lock. Re-check after acquiring the
                 * lock so a retry never locks the same amount twice.
                 */
                $existing = $this->existingRequest(
                    $actor,
                    $idempotencyKey
                );

                if ($existing) {
                    $this->assertSameRequest(
                        $existing,
                        $building,
                        $bankAccount,
                        $actor,
                        $amount
                    );

                    $this->wallets->unlockFunds(
                        $wallet,
                        $amount
                    );

                    return $existing;
                }

                return WalletPayoutRequest::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'building_id' => $building->getKey(),
                    'wallet_id' => $wallet->getKey(),
                    'building_bank_account_id' => $bankAccount->getKey(),
                    'amount' => $amount,
                    'fee_amount' => 0,
                    'net_amount' => $amount,
                    'status' => WalletPayoutStatus::Pending,
                    'requested_by' => $actor->getKey(),
                ])->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existingRequest(
                $actor,
                $idempotencyKey
            );

            if (! $existing) {
                throw $exception;
            }

            $this->assertSameRequest(
                $existing,
                $building,
                $bankAccount,
                $actor,
                $amount
            );

            return $existing;
        }
    }

    public function approve(
        WalletPayoutRequest $request,
        User $actor
    ): WalletPayoutRequest {
        return DB::transaction(function () use (
            $request,
            $actor
        ): WalletPayoutRequest {
            $request = WalletPayoutRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($request->status === WalletPayoutStatus::Approved) {
                return $request;
            }

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
        }, 3);
    }

    public function reject(
        WalletPayoutRequest $request,
        User $actor,
        ?string $reason = null
    ): WalletPayoutRequest {
        return DB::transaction(function () use (
            $request,
            $actor,
            $reason
        ): WalletPayoutRequest {
            $request = WalletPayoutRequest::query()
                ->with('wallet')
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($request->status === WalletPayoutStatus::Rejected) {
                return $request;
            }

            if (! in_array(
                $request->status,
                [
                    WalletPayoutStatus::Pending,
                    WalletPayoutStatus::Approved,
                ],
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
        }, 3);
    }

    public function markPaid(
        WalletPayoutRequest $request,
        User $actor,
        string $bankReference
    ): WalletPayoutRequest {
        return DB::transaction(function () use (
            $request,
            $actor,
            $bankReference
        ): WalletPayoutRequest {
            $request = WalletPayoutRequest::query()
                ->with('wallet')
                ->lockForUpdate()
                ->findOrFail($request->getKey());

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
        }, 3);
    }

    private function existingRequest(
        User $actor,
        ?string $idempotencyKey
    ): ?WalletPayoutRequest {
        if ($idempotencyKey === null) {
            return null;
        }

        return WalletPayoutRequest::query()
            ->where('requested_by', $actor->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function assertSameRequest(
        WalletPayoutRequest $existing,
        Building $building,
        BuildingBankAccount $bankAccount,
        User $actor,
        int $amount
    ): void {
        $same = (int) $existing->building_id
                === (int) $building->getKey()
            && (int) $existing->building_bank_account_id
                === (int) $bankAccount->getKey()
            && (int) $existing->requested_by
                === (int) $actor->getKey()
            && (int) $existing->amount === $amount;

        if (! $same) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The idempotency key has already been used for a different building payout operation.',
            ]);
        }
    }

    private function assertBankAccount(
        Building $building,
        BuildingBankAccount $bankAccount
    ): void {
        if (
            (int) $bankAccount->building_id !== (int) $building->getKey()
            || ! $bankAccount->is_active
            || ! $bankAccount->is_verified
        ) {
            throw ValidationException::withMessages([
                'building_bank_account_id' => 'A verified active bank account of this building is required.',
            ]);
        }
    }

    private function normalizeIdempotencyKey(?string $key): ?string
    {
        $key = trim((string) $key);

        return $key === '' ? null : $key;
    }
}
