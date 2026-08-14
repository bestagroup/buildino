<?php

namespace App\Services\Wallet;

use App\Enums\PaymentStatus;
use App\Enums\WalletTopUpStatus;
use App\Enums\WalletTransferType;
use App\Models\Building;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\WalletTopUp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WalletTopUpService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly WalletOutstandingRetryService $retry
    ) {
    }

    public function create(
        Building $building,
        User $payer,
        Model $target,
        array $data
    ): WalletTopUp {
        $this->validateTarget(
            $building,
            $payer,
            $target
        );

        $amount = (int) $data['amount'];
        $currency = strtoupper(
            $building->currency ?: 'IRR'
        );

        $wallet = $this->wallets->walletFor(
            $target,
            $currency
        );

        return DB::transaction(function () use (
            $building,
            $payer,
            $target,
            $data,
            $amount,
            $currency,
            $wallet
        ): WalletTopUp {
            $existingTransaction = PaymentTransaction::query()
                ->where(
                    'idempotency_key',
                    $data['idempotency_key']
                )
                ->lockForUpdate()
                ->first();

            if ($existingTransaction) {
                return $this->existingTopUp(
                    $existingTransaction,
                    $building,
                    $payer,
                    $target,
                    $wallet,
                    $amount
                );
            }

            $payment = Payment::query()->create([
                'uuid' => (string) Str::uuid(),
                'building_id' => $building->getKey(),
                'payer_user_id' => $payer->getKey(),
                'payment_number' => sprintf(
                    'TOP-%d-%s',
                    $building->getKey(),
                    strtoupper(Str::random(12))
                ),
                'amount' => $amount,
                'currency' => $currency,
                'method' => $data['method'],
                'status' => PaymentStatus::Pending,
                'description' => $data['description']
                    ?? 'External wallet top-up',
            ]);

            $payment->paymentTransactions()->create([
                'gateway' => $data['gateway'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'authority' => null,
                'request_payload' => [
                    'purpose' => 'wallet_topup',
                    'target_type' => $target->getMorphClass(),
                    'target_id' => $target->getKey(),
                    'wallet_id' => $wallet->getKey(),
                    'amount' => $amount,
                    'currency' => $currency,
                ],
                'requested_at' => now(),
            ]);

            return WalletTopUp::query()->create([
                'uuid' => (string) Str::uuid(),
                'payment_id' => $payment->getKey(),
                'wallet_id' => $wallet->getKey(),
                'target_type' => $target->getMorphClass(),
                'target_id' => $target->getKey(),
                'amount' => $amount,
                'currency' => $currency,
                'status' => WalletTopUpStatus::Pending,
            ])->refresh();
        }, 3);
    }

    public function credit(
        WalletTopUp $topUp,
        ?User $verifiedBy
    ): WalletTopUp {
        return DB::transaction(function () use (
            $topUp,
            $verifiedBy
        ): WalletTopUp {
            $topUp = WalletTopUp::query()
                ->with([
                    'payment',
                    'wallet',
                ])
                ->lockForUpdate()
                ->findOrFail($topUp->getKey());

            if (
                $topUp->status
                === WalletTopUpStatus::Credited
            ) {
                return $topUp;
            }

            if (
                $topUp->status
                !== WalletTopUpStatus::Pending
            ) {
                throw ValidationException::withMessages([
                    'wallet_topup' =>
                        'Wallet top-up cannot be credited in its current status.',
                ]);
            }

            if (
                ! $topUp->payment
                || (int) $topUp->payment->amount
                    !== (int) $topUp->amount
                || strtoupper($topUp->payment->currency)
                    !== strtoupper($topUp->currency)
            ) {
                throw ValidationException::withMessages([
                    'wallet_topup' =>
                        'Wallet top-up does not match its external payment.',
                ]);
            }

            if (! $topUp->wallet) {
                throw ValidationException::withMessages([
                    'wallet' => 'Target wallet does not exist.',
                ]);
            }

            if (
                $topUp->wallet->owner_type
                    !== $topUp->target_type
                || (int) $topUp->wallet->owner_id
                    !== (int) $topUp->target_id
            ) {
                throw ValidationException::withMessages([
                    'wallet' =>
                        'Target wallet owner does not match the top-up target.',
                ]);
            }

            $transfer = $this->wallets->credit(
                $topUp->wallet,
                (int) $topUp->amount,
                WalletTransferType::TopUp,
                sprintf(
                    'external-payment:%d:wallet-topup',
                    $topUp->payment_id
                ),
                $topUp->payment,
                $verifiedBy,
                'External payment credited to wallet'
            );

            $topUp->update([
                'status' => WalletTopUpStatus::Credited,
                'wallet_transfer_id' => $transfer->getKey(),
                'credited_at' => now(),
            ]);

            return $topUp->refresh();
        }, 3);
    }

    public function retryOutstanding(
        WalletTopUp $topUp,
        User $actor
    ): WalletTopUp {
        try {
            $summary = $this->retry->retry(
                $topUp,
                $actor
            );
        } catch (\Throwable $exception) {
            /*
             * A successful external payment and wallet credit must never
             * be rolled back merely because automatic debt collection
             * cannot complete. The result remains auditable/retryable.
             */
            $summary = [
                'status' => 'error',
                'exception' => class_basename($exception),
                'message' => $exception->getMessage(),
            ];
        }

        $topUp->update([
            'retry_attempted_at' => now(),
            'retry_summary' => $summary,
        ]);

        return $topUp->refresh();
    }

    private function validateTarget(
        Building $building,
        User $payer,
        Model $target
    ): void {
        if ($target instanceof User) {
            if ((int) $target->getKey() !== (int) $payer->getKey()) {
                throw ValidationException::withMessages([
                    'target' =>
                        'A user may only top up their own personal wallet.',
                ]);
            }

            return;
        }

        if ($target instanceof Unit) {
            $target->loadMissing(
                'floor.block.building'
            );

            if (
                (int) $target->floor?->block?->building_id
                !== (int) $building->getKey()
            ) {
                throw ValidationException::withMessages([
                    'unit_id' =>
                        'Target unit does not belong to this building.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'target' =>
                'Only user and unit wallets may receive external top-ups.',
        ]);
    }

    private function existingTopUp(
        PaymentTransaction $transaction,
        Building $building,
        User $payer,
        Model $target,
        $wallet,
        int $amount
    ): WalletTopUp {
        $transaction->loadMissing(
            'payment.walletTopUp'
        );

        $payment = $transaction->payment;
        $topUp = $payment?->walletTopUp;

        if (
            ! $payment
            || ! $topUp
            || (int) $payment->building_id
                !== (int) $building->getKey()
            || (int) $payment->payer_user_id
                !== (int) $payer->getKey()
            || (int) $payment->amount !== $amount
            || (int) $topUp->wallet_id
                !== (int) $wallet->getKey()
            || $topUp->target_type
                !== $target->getMorphClass()
            || (int) $topUp->target_id
                !== (int) $target->getKey()
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' =>
                    'This idempotency key is already used by another payment request.',
            ]);
        }

        return $topUp;
    }
}
