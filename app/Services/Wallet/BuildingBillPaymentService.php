<?php

namespace App\Services\Wallet;

use App\Enums\BuildingBillPaymentStatus;
use App\Enums\BuildingBillType;
use App\Enums\WalletTransferType;
use App\Models\Building;
use App\Models\BuildingBillPayment;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BuildingBillPaymentService
{
    public function __construct(
        private readonly WalletService $wallets
    ) {
    }

    public function request(
        Building $building,
        User $actor,
        BuildingBillType $type,
        int $amount,
        array $data = []
    ): BuildingBillPayment {
        $wallet = $this->wallets->walletFor($building);

        $this->wallets->lockFunds($wallet, $amount);

        try {
            return BuildingBillPayment::query()->create([
                'uuid' => (string) Str::uuid(),
                'building_id' => $building->getKey(),
                'wallet_id' => $wallet->getKey(),
                'bill_type' => $type,
                'bill_identifier' => $data['bill_identifier'] ?? null,
                'payment_identifier' => $data['payment_identifier'] ?? null,
                'amount' => $amount,
                'status' => BuildingBillPaymentStatus::Pending,
                'requested_by' => $actor->getKey(),
                'provider' => $data['provider'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->wallets->unlockFunds($wallet, $amount);
            throw $e;
        }
    }

    public function complete(
        BuildingBillPayment $bill,
        User $actor,
        ?string $providerReference = null,
        ?array $providerPayload = null
    ): BuildingBillPayment {
        $bill->refresh();

        if ($bill->status === BuildingBillPaymentStatus::Paid) {
            return $bill;
        }

        if ($bill->status !== BuildingBillPaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending bill payments can be completed.',
            ]);
        }

        $transfer = $this->wallets->debitLocked(
            $bill->wallet,
            (int) $bill->amount,
            WalletTransferType::BillPayment,
            'building-bill:'.$bill->getKey().':paid',
            $bill,
            $actor,
            'Building utility bill payment'
        );

        $bill->update([
            'status' => BuildingBillPaymentStatus::Paid,
            'wallet_transfer_id' => $transfer->getKey(),
            'completed_by' => $actor->getKey(),
            'provider_reference' => $providerReference,
            'provider_payload' => $providerPayload,
            'completed_at' => now(),
        ]);

        return $bill->refresh();
    }

    public function fail(
        BuildingBillPayment $bill,
        User $actor,
        ?string $reason = null
    ): BuildingBillPayment {
        $bill->refresh();

        if ($bill->status !== BuildingBillPaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending bill payments can fail.',
            ]);
        }

        $this->wallets->unlockFunds(
            $bill->wallet,
            (int) $bill->amount
        );

        $bill->update([
            'status' => BuildingBillPaymentStatus::Failed,
            'completed_by' => $actor->getKey(),
            'failure_reason' => $reason,
            'failed_at' => now(),
        ]);

        return $bill->refresh();
    }
}
