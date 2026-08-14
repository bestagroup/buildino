<?php

namespace App\Services\Wallet;

use App\Events\WalletTransferCompleted;
use App\Enums\WalletEntryType;
use App\Enums\WalletTransferStatus;
use App\Enums\WalletTransferType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransfer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WalletService
{
    public function walletFor(
        Model $owner,
        string $currency = 'IRR'
    ): Wallet {
        return Wallet::query()->firstOrCreate(
            [
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'currency' => strtoupper($currency),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'balance' => 0,
                'locked_balance' => 0,
                'is_active' => true,
            ]
        );
    }

    public function credit(
        Wallet $wallet,
        int $amount,
        WalletTransferType $type,
        string $idempotencyKey,
        ?Model $reference = null,
        ?User $actor = null,
        ?string $description = null
    ): WalletTransfer {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Wallet credit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $wallet,
            $amount,
            $type,
            $idempotencyKey,
            $reference,
            $actor,
            $description
        ): WalletTransfer {
            $existing = WalletTransfer::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                $this->dispatchAccountingIfCompleted(
                    $existing
                );

                return $existing;
            }

            $wallet = Wallet::query()
                ->lockForUpdate()
                ->findOrFail($wallet->getKey());

            $this->assertActive($wallet);

            $transfer = $this->createTransfer(
                null,
                $wallet,
                $amount,
                $type,
                $idempotencyKey,
                $reference,
                $actor,
                $description
            );

            $wallet->increment('balance', $amount);
            $wallet->refresh();

            $transfer->entries()->create([
                'wallet_id' => $wallet->getKey(),
                'entry_type' => WalletEntryType::Credit,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
            ]);

            return $this->completeTransfer(
                $transfer
            );
        }, 3);
    }

    public function transfer(
        Wallet $source,
        Wallet $destination,
        int $amount,
        WalletTransferType $type,
        string $idempotencyKey,
        ?Model $reference = null,
        ?User $actor = null,
        ?string $description = null
    ): WalletTransfer {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Transfer amount must be greater than zero.',
            ]);
        }

        if ($source->is($destination)) {
            throw ValidationException::withMessages([
                'wallet' => 'Source and destination wallets must be different.',
            ]);
        }

        return DB::transaction(function () use (
            $source,
            $destination,
            $amount,
            $type,
            $idempotencyKey,
            $reference,
            $actor,
            $description
        ): WalletTransfer {
            $existing = WalletTransfer::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                $this->dispatchAccountingIfCompleted(
                    $existing
                );

                return $existing;
            }

            $wallets = Wallet::query()
                ->whereIn(
                    'id',
                    [
                        $source->getKey(),
                        $destination->getKey(),
                    ]
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $source = $wallets->get($source->getKey());
            $destination = $wallets->get($destination->getKey());

            if (! $source || ! $destination) {
                throw ValidationException::withMessages([
                    'wallet' => 'Wallet not found.',
                ]);
            }

            $this->assertActive($source);
            $this->assertActive($destination);

            if ($source->currency !== $destination->currency) {
                throw ValidationException::withMessages([
                    'currency' => 'Wallet currencies must match.',
                ]);
            }

            if ($source->availableBalance() < $amount) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient available wallet balance.',
                ]);
            }

            $transfer = $this->createTransfer(
                $source,
                $destination,
                $amount,
                $type,
                $idempotencyKey,
                $reference,
                $actor,
                $description
            );

            $source->decrement('balance', $amount);
            $destination->increment('balance', $amount);

            $source->refresh();
            $destination->refresh();

            $transfer->entries()->create([
                'wallet_id' => $source->getKey(),
                'entry_type' => WalletEntryType::Debit,
                'amount' => $amount,
                'balance_after' => $source->balance,
            ]);

            $transfer->entries()->create([
                'wallet_id' => $destination->getKey(),
                'entry_type' => WalletEntryType::Credit,
                'amount' => $amount,
                'balance_after' => $destination->balance,
            ]);

            return $this->completeTransfer(
                $transfer
            );
        }, 3);
    }


    public function lockFunds(Wallet $wallet, int $amount): Wallet
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Locked amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($wallet, $amount): Wallet {
            $wallet = Wallet::query()
                ->lockForUpdate()
                ->findOrFail($wallet->getKey());

            $this->assertActive($wallet);

            if ($wallet->availableBalance() < $amount) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient available wallet balance.',
                ]);
            }

            $wallet->increment('locked_balance', $amount);

            return $wallet->refresh();
        }, 3);
    }

    public function unlockFunds(Wallet $wallet, int $amount): Wallet
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Unlocked amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($wallet, $amount): Wallet {
            $wallet = Wallet::query()
                ->lockForUpdate()
                ->findOrFail($wallet->getKey());

            if ((int) $wallet->locked_balance < $amount) {
                throw ValidationException::withMessages([
                    'locked_balance' => 'Wallet locked balance is lower than requested unlock amount.',
                ]);
            }

            $wallet->decrement('locked_balance', $amount);

            return $wallet->refresh();
        }, 3);
    }

    public function debitLocked(
        Wallet $wallet,
        int $amount,
        WalletTransferType $type,
        string $idempotencyKey,
        ?Model $reference = null,
        ?User $actor = null,
        ?string $description = null
    ): WalletTransfer {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Wallet debit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $wallet,
            $amount,
            $type,
            $idempotencyKey,
            $reference,
            $actor,
            $description
        ): WalletTransfer {
            $existing = WalletTransfer::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                $this->dispatchAccountingIfCompleted(
                    $existing
                );

                return $existing;
            }

            $wallet = Wallet::query()
                ->lockForUpdate()
                ->findOrFail($wallet->getKey());

            $this->assertActive($wallet);

            if (
                (int) $wallet->locked_balance < $amount
                || (int) $wallet->balance < $amount
            ) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient locked wallet balance.',
                ]);
            }

            $transfer = $this->createTransfer(
                $wallet,
                null,
                $amount,
                $type,
                $idempotencyKey,
                $reference,
                $actor,
                $description
            );

            $wallet->decrement('balance', $amount);
            $wallet->decrement('locked_balance', $amount);
            $wallet->refresh();

            $transfer->entries()->create([
                'wallet_id' => $wallet->getKey(),
                'entry_type' => WalletEntryType::Debit,
                'amount' => $amount,
                'balance_after' => $wallet->balance,
            ]);

            return $this->completeTransfer(
                $transfer
            );
        }, 3);
    }


    public function transferLocked(
        Wallet $source,
        Wallet $destination,
        int $amount,
        WalletTransferType $type,
        string $idempotencyKey,
        ?Model $reference = null,
        ?User $actor = null,
        ?string $description = null
    ): WalletTransfer {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Locked transfer amount must be greater than zero.',
            ]);
        }

        if ($source->is($destination)) {
            throw ValidationException::withMessages([
                'wallet' => 'Source and destination wallets must be different.',
            ]);
        }

        return DB::transaction(function () use (
            $source,
            $destination,
            $amount,
            $type,
            $idempotencyKey,
            $reference,
            $actor,
            $description
        ): WalletTransfer {
            $existing = WalletTransfer::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                $this->dispatchAccountingIfCompleted(
                    $existing
                );

                return $existing;
            }

            $wallets = Wallet::query()
                ->whereIn(
                    'id',
                    [
                        $source->getKey(),
                        $destination->getKey(),
                    ]
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $source = $wallets->get(
                $source->getKey()
            );

            $destination = $wallets->get(
                $destination->getKey()
            );

            if (! $source || ! $destination) {
                throw ValidationException::withMessages([
                    'wallet' => 'Wallet not found.',
                ]);
            }

            $this->assertActive($source);
            $this->assertActive($destination);

            if ($source->currency !== $destination->currency) {
                throw ValidationException::withMessages([
                    'currency' => 'Wallet currencies must match.',
                ]);
            }

            if (
                (int) $source->locked_balance < $amount
                || (int) $source->balance < $amount
            ) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient locked wallet balance.',
                ]);
            }

            $transfer = $this->createTransfer(
                $source,
                $destination,
                $amount,
                $type,
                $idempotencyKey,
                $reference,
                $actor,
                $description
            );

            $source->decrement(
                'balance',
                $amount
            );

            $source->decrement(
                'locked_balance',
                $amount
            );

            $destination->increment(
                'balance',
                $amount
            );

            $source->refresh();
            $destination->refresh();

            $transfer->entries()->create([
                'wallet_id' => $source->getKey(),
                'entry_type' => WalletEntryType::Debit,
                'amount' => $amount,
                'balance_after' => $source->balance,
            ]);

            $transfer->entries()->create([
                'wallet_id' => $destination->getKey(),
                'entry_type' => WalletEntryType::Credit,
                'amount' => $amount,
                'balance_after' => $destination->balance,
            ]);

            return $this->completeTransfer(
                $transfer
            );
        }, 3);
    }


    private function completeTransfer(
        WalletTransfer $transfer
    ): WalletTransfer {
        $transfer->update([
            'status' =>
                WalletTransferStatus::Completed,
            'completed_at' => now(),
        ]);

        $transfer = $transfer->refresh();

        $this->dispatchAccountingIfCompleted(
            $transfer
        );

        return $transfer;
    }

    private function dispatchAccountingIfCompleted(
        WalletTransfer $transfer
    ): void {
        if (
            $transfer->status
            !== WalletTransferStatus::Completed
        ) {
            return;
        }

        $transferId = $transfer->getKey();

        DB::afterCommit(
            static fn () =>
                WalletTransferCompleted::dispatch(
                    $transferId
                )
        );
    }

    private function createTransfer(
        ?Wallet $source,
        ?Wallet $destination,
        int $amount,
        WalletTransferType $type,
        string $idempotencyKey,
        ?Model $reference,
        ?User $actor,
        ?string $description
    ): WalletTransfer {
        return WalletTransfer::query()->create([
            'uuid' => (string) Str::uuid(),
            'source_wallet_id' => $source?->getKey(),
            'destination_wallet_id' => $destination?->getKey(),
            'amount' => $amount,
            'currency' => $source?->currency
                ?? $destination?->currency
                ?? 'IRR',
            'type' => $type,
            'status' => WalletTransferStatus::Pending,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'idempotency_key' => $idempotencyKey,
            'description' => $description,
            'created_by' => $actor?->getKey(),
        ]);
    }

    private function assertActive(Wallet $wallet): void
    {
        if (! $wallet->is_active) {
            throw ValidationException::withMessages([
                'wallet' => 'Wallet is inactive.',
            ]);
        }
    }
}
