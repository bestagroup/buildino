<?php

namespace App\Services\Facility;

use App\Enums\FacilityWalletPayerSource;
use App\Enums\RefundStatus;
use App\Enums\ReservationApprovalType;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransferType;
use App\Models\FacilityReservation;
use App\Models\ReservationCancellation;
use App\Models\ReservationWalletPayment;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FacilityWalletPaymentService
{
    public function __construct(
        private readonly WalletService $wallets
    ) {
    }

    public function pay(
        FacilityReservation $reservation,
        User $actor,
        FacilityWalletPayerSource $payerSource
    ): ReservationWalletPayment {
        return DB::transaction(function () use (
            $reservation,
            $actor,
            $payerSource
        ): ReservationWalletPayment {
            $reservation = FacilityReservation::query()
                ->with([
                    'buildingFacility.building',
                    'unit',
                    'user',
                    'walletPayment',
                ])
                ->lockForUpdate()
                ->findOrFail($reservation->getKey());

            if ((int) $reservation->user_id !== (int) $actor->getKey()) {
                throw ValidationException::withMessages([
                    'user' => 'Only the reservation owner can pay this reservation.',
                ]);
            }

            if ((int) $reservation->final_amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This reservation does not require payment.',
                ]);
            }

            if ($reservation->walletPayment) {
                return $reservation->walletPayment;
            }

            if ($reservation->status !== ReservationStatus::PaymentPending) {
                throw ValidationException::withMessages([
                    'status' => 'Reservation is not awaiting payment.',
                ]);
            }

            $building = $reservation->buildingFacility?->building;

            if (! $building) {
                throw ValidationException::withMessages([
                    'building' => 'Reservation facility has no building.',
                ]);
            }

            $source = $payerSource === FacilityWalletPayerSource::UserWallet
                ? $this->wallets->walletFor($actor)
                : $this->wallets->walletFor($reservation->unit);

            $destination = $this->wallets->walletFor($building);

            $transfer = $this->wallets->transfer(
                $source,
                $destination,
                (int) $reservation->final_amount,
                WalletTransferType::FacilityFee,
                'facility-reservation:'.$reservation->getKey().':payment',
                $reservation,
                $actor,
                'Facility reservation fee'
            );

            $payment = ReservationWalletPayment::query()->create([
                'facility_reservation_id' => $reservation->getKey(),
                'wallet_transfer_id' => $transfer->getKey(),
                'source_wallet_id' => $source->getKey(),
                'building_wallet_id' => $destination->getKey(),
                'payer_source' => $payerSource,
                'amount' => (int) $reservation->final_amount,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            if ($reservation->approval_type === ReservationApprovalType::Automatic) {
                $reservation->update([
                    'status' => ReservationStatus::Approved,
                    'approved_at' => now(),
                ]);
            } else {
                $reservation->update([
                    'status' => ReservationStatus::Pending,
                ]);
            }

            return $payment->refresh();
        }, 3);
    }

    public function refund(
        ReservationCancellation $cancellation,
        ?User $actor = null
    ): ReservationCancellation {
        return DB::transaction(function () use (
            $cancellation,
            $actor
        ): ReservationCancellation {
            $cancellation = ReservationCancellation::query()
                ->with('reservation.walletPayment')
                ->lockForUpdate()
                ->findOrFail($cancellation->getKey());

            if ((int) $cancellation->refund_amount <= 0) {
                return $cancellation;
            }

            if ($cancellation->refund_wallet_transfer_id) {
                return $cancellation;
            }

            $payment = $cancellation->reservation?->walletPayment;

            if (! $payment) {
                $cancellation->update([
                    'refund_status' => RefundStatus::Cancelled,
                ]);

                return $cancellation->refresh();
            }

            $buildingWallet = $payment->buildingWallet()->firstOrFail();
            $sourceWallet = $payment->sourceWallet()->firstOrFail();

            $transfer = $this->wallets->transfer(
                $buildingWallet,
                $sourceWallet,
                (int) $cancellation->refund_amount,
                WalletTransferType::Refund,
                'facility-cancellation:'.$cancellation->getKey().':refund',
                $cancellation,
                $actor,
                'Facility reservation refund'
            );

            $cancellation->update([
                'refund_wallet_transfer_id' => $transfer->getKey(),
                'refund_status' => RefundStatus::Refunded,
            ]);

            return $cancellation->refresh();
        }, 3);
    }
}
