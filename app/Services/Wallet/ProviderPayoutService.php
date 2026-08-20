<?php

namespace App\Services\Wallet;

use App\Enums\WalletPayoutStatus;
use App\Enums\WalletTransferType;
use App\Models\ProviderBankAccount;
use App\Models\ProviderPayoutRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProviderPayoutService
{
    public function __construct(
        private readonly WalletService $wallets
    ) {
    }

    public function request(
        User $provider,
        ProviderBankAccount $bankAccount,
        int $amount,
        string $currency = 'IRR',
        ?string $idempotencyKey = null
    ): ProviderPayoutRequest {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payout amount must be greater than zero.',
            ]);
        }

        $currency = strtoupper($currency);
        $idempotencyKey = $this->normalizeIdempotencyKey(
            $idempotencyKey
        );

        try {
            return DB::transaction(function () use (
                $provider,
                $bankAccount,
                $amount,
                $currency,
                $idempotencyKey
            ): ProviderPayoutRequest {
                $existing = $this->existingRequest(
                    $provider,
                    $idempotencyKey
                );

                if ($existing) {
                    $this->assertSameRequest(
                        $existing,
                        $provider,
                        $bankAccount,
                        $amount,
                        $currency
                    );

                    return $existing;
                }

                $bankAccount = ProviderBankAccount::query()
                    ->lockForUpdate()
                    ->findOrFail($bankAccount->getKey());

                $this->assertBankAccount($provider, $bankAccount);

                $existing = $this->existingRequest(
                    $provider,
                    $idempotencyKey
                );

                if ($existing) {
                    $this->assertSameRequest(
                        $existing,
                        $provider,
                        $bankAccount,
                        $amount,
                        $currency
                    );

                    return $existing;
                }

                $wallet = $this->wallets->walletFor(
                    $provider,
                    $currency
                );

                $this->wallets->lockFunds(
                    $wallet,
                    $amount
                );

                $existing = $this->existingRequest(
                    $provider,
                    $idempotencyKey
                );

                if ($existing) {
                    $this->assertSameRequest(
                        $existing,
                        $provider,
                        $bankAccount,
                        $amount,
                        $currency
                    );

                    $this->wallets->unlockFunds(
                        $wallet,
                        $amount
                    );

                    return $existing;
                }

                return ProviderPayoutRequest::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'provider_user_id' => $provider->getKey(),
                    'wallet_id' => $wallet->getKey(),
                    'provider_bank_account_id' => $bankAccount->getKey(),
                    'amount' => $amount,
                    'fee_amount' => 0,
                    'net_amount' => $amount,
                    'status' => WalletPayoutStatus::Pending,
                    'requested_by' => $provider->getKey(),
                ])->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existingRequest(
                $provider,
                $idempotencyKey
            );

            if (! $existing) {
                throw $exception;
            }

            $this->assertSameRequest(
                $existing,
                $provider,
                $bankAccount,
                $amount,
                $currency
            );

            return $existing;
        }
    }

    public function approve(
        ProviderPayoutRequest $request,
        User $actor
    ): ProviderPayoutRequest {
        return DB::transaction(function () use (
            $request,
            $actor
        ): ProviderPayoutRequest {
            $request = ProviderPayoutRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($request->status === WalletPayoutStatus::Approved) {
                return $request;
            }

            if ($request->status !== WalletPayoutStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending provider payout requests can be approved.',
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
        ProviderPayoutRequest $request,
        User $actor,
        ?string $reason = null
    ): ProviderPayoutRequest {
        return DB::transaction(function () use (
            $request,
            $actor,
            $reason
        ): ProviderPayoutRequest {
            $request = ProviderPayoutRequest::query()
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
                    'status' => 'Provider payout cannot be rejected in its current status.',
                ]);
            }

            $this->wallets->unlockFunds(
                $request->wallet,
                (int) $request->amount
            );

            $request->update([
                'status' => WalletPayoutStatus::Rejected,
                'rejection_reason' => $reason,
                'rejected_at' => now(),
            ]);

            return $request->refresh();
        }, 3);
    }

    public function markPaid(
        ProviderPayoutRequest $request,
        User $actor,
        string $bankReference
    ): ProviderPayoutRequest {
        return DB::transaction(function () use (
            $request,
            $actor,
            $bankReference
        ): ProviderPayoutRequest {
            $request = ProviderPayoutRequest::query()
                ->with('wallet')
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if ($request->status === WalletPayoutStatus::Paid) {
                return $request;
            }

            if ($request->status !== WalletPayoutStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved provider payout requests can be marked as paid.',
                ]);
            }

            $transfer = $this->wallets->debitLocked(
                $request->wallet,
                (int) $request->amount,
                WalletTransferType::ProviderPayout,
                'provider-payout:'.$request->getKey().':paid',
                $request,
                $actor,
                'Provider wallet payout'
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
        User $provider,
        ?string $idempotencyKey
    ): ?ProviderPayoutRequest {
        if ($idempotencyKey === null) {
            return null;
        }

        return ProviderPayoutRequest::query()
            ->with('wallet')
            ->where('requested_by', $provider->getKey())
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function assertSameRequest(
        ProviderPayoutRequest $existing,
        User $provider,
        ProviderBankAccount $bankAccount,
        int $amount,
        string $currency
    ): void {
        $same = (int) $existing->provider_user_id
                === (int) $provider->getKey()
            && (int) $existing->provider_bank_account_id
                === (int) $bankAccount->getKey()
            && (int) $existing->requested_by
                === (int) $provider->getKey()
            && (int) $existing->amount === $amount
            && strtoupper((string) $existing->wallet?->currency)
                === strtoupper($currency);

        if (! $same) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The idempotency key has already been used for a different provider payout operation.',
            ]);
        }
    }

    private function assertBankAccount(
        User $provider,
        ProviderBankAccount $bankAccount
    ): void {
        if (
            (int) $bankAccount->user_id !== (int) $provider->getKey()
            || ! $bankAccount->is_active
            || ! $bankAccount->is_verified
        ) {
            throw ValidationException::withMessages([
                'provider_bank_account_id' => 'A verified active bank account owned by the provider is required.',
            ]);
        }
    }

    private function normalizeIdempotencyKey(?string $key): ?string
    {
        $key = trim((string) $key);

        return $key === '' ? null : $key;
    }
}
