<?php

namespace App\Services\ServiceMarketplace;

use App\Enums\ServiceRequestPayerSource;
use App\Enums\ServiceRequestQuoteStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestWalletPaymentStatus;
use App\Enums\WalletTransferType;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestQuote;
use App\Models\ServiceRequestWalletPayment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Security\UnitResidentAccessService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ServiceRequestMarketplaceService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly PlatformWalletAccountService $platformAccounts,
        private readonly BuildingServiceFinancialSettingService $settings,
        private readonly UnitResidentAccessService $residentAccess
    ) {
    }

    public function createQuote(
        ServiceRequest $request,
        int $amount,
        ?string $notes = null,
        ?string $validUntil = null
    ): ServiceRequestQuote {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Service quote amount must be greater than zero.',
            ]);
        }

        $request->loadMissing([
            'building',
            'assignedTo',
        ]);

        if (! $request->assignedTo) {
            throw ValidationException::withMessages([
                'assigned_to' =>
                    'A service provider must be assigned before quoting.',
            ]);
        }

        if (in_array(
            $request->status,
            [
                ServiceRequestStatus::Completed,
                ServiceRequestStatus::Cancelled,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'status' =>
                    'A completed or cancelled service request cannot be quoted.',
            ]);
        }

        $activePaymentExists = ServiceRequestWalletPayment::query()
            ->where(
                'service_request_id',
                $request->getKey()
            )
            ->whereIn(
                'status',
                [
                    ServiceRequestWalletPaymentStatus::Locked->value,
                    ServiceRequestWalletPaymentStatus::Settled->value,
                ]
            )
            ->exists();

        if ($activePaymentExists) {
            throw ValidationException::withMessages([
                'payment' =>
                    'An active service payment already exists for this request.',
            ]);
        }

        $setting = $this->settings->forBuilding(
            $request->building
        );

        if (! $setting->is_active) {
            throw ValidationException::withMessages([
                'service_finance' =>
                    'Service financial operations are disabled for this building.',
            ]);
        }

        $rate = (int) $setting->platform_commission_bps;

        if ($rate < 0 || $rate > 10000) {
            throw ValidationException::withMessages([
                'platform_commission_bps' =>
                    'Platform commission must be between 0 and 10000 basis points.',
            ]);
        }

        $commission = intdiv(
            $amount * $rate,
            10000
        );

        $providerAmount = $amount - $commission;

        return DB::transaction(function () use (
            $request,
            $amount,
            $notes,
            $validUntil,
            $rate,
            $commission,
            $providerAmount
        ): ServiceRequestQuote {
            ServiceRequestQuote::query()
                ->where(
                    'service_request_id',
                    $request->getKey()
                )
                ->where(
                    'status',
                    ServiceRequestQuoteStatus::Pending->value
                )
                ->update([
                    'status' =>
                        ServiceRequestQuoteStatus::Rejected->value,
                ]);

            return ServiceRequestQuote::query()->create([
                'uuid' => (string) Str::uuid(),
                'service_request_id' =>
                    $request->getKey(),
                'provider_user_id' =>
                    $request->assigned_to,
                'amount' => $amount,
                'commission_rate_bps' => $rate,
                'commission_amount' => $commission,
                'provider_amount' => $providerAmount,
                'status' =>
                    ServiceRequestQuoteStatus::Pending,
                'notes' => $notes,
                'valid_until' => $validUntil,
            ])->refresh();
        }, 3);
    }

    public function acceptQuote(
        ServiceRequestQuote $quote,
        User $requester,
        ServiceRequestPayerSource $payerSource
    ): ServiceRequestWalletPayment {
        return DB::transaction(function () use (
            $quote,
            $requester,
            $payerSource
        ): ServiceRequestWalletPayment {
            $quote = ServiceRequestQuote::query()
                ->with([
                    'serviceRequest.building',
                    'serviceRequest.unit.floor.block.building',
                    'serviceRequest.requestedBy',
                    'provider',
                ])
                ->lockForUpdate()
                ->findOrFail($quote->getKey());

            $request = $quote->serviceRequest;

            if (
                (int) $request->requested_by
                !== (int) $requester->getKey()
            ) {
                throw ValidationException::withMessages([
                    'requester' =>
                        'Only the service requester may authorize wallet payment.',
                ]);
            }

            if (
                $quote->status
                !== ServiceRequestQuoteStatus::Pending
            ) {
                throw ValidationException::withMessages([
                    'quote' =>
                        'Only a pending quote may be accepted.',
                ]);
            }

            if (
                $quote->valid_until
                && $quote->valid_until->isPast()
            ) {
                $quote->update([
                    'status' =>
                        ServiceRequestQuoteStatus::Expired,
                ]);

                throw ValidationException::withMessages([
                    'quote' =>
                        'The service quote has expired.',
                ]);
            }

            if (in_array(
                $request->status,
                [
                    ServiceRequestStatus::Completed,
                    ServiceRequestStatus::Cancelled,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' =>
                        'This service request can no longer accept payment.',
                ]);
            }

            $existing = ServiceRequestWalletPayment::query()
                ->where(
                    'service_request_id',
                    $request->getKey()
                )
                ->whereIn(
                    'status',
                    [
                        ServiceRequestWalletPaymentStatus::Locked->value,
                        ServiceRequestWalletPaymentStatus::Settled->value,
                    ]
                )
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (
                    (int) $existing->service_request_quote_id
                    === (int) $quote->getKey()
                ) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'payment' =>
                        'This service request already has an active wallet payment.',
                ]);
            }

            $setting = $this->settings->forBuilding(
                $request->building
            );

            $source = $this->resolveSourceWallet(
                $request,
                $requester,
                $payerSource,
                $setting
            );

            $currency = strtoupper(
                $request->building->currency ?: 'IRR'
            );

            $providerWallet = $this->wallets->walletFor(
                $quote->provider,
                $currency
            );

            $platformWallet =
                $this->platformAccounts
                    ->marketplaceWallet($currency);

            $this->wallets->lockFunds(
                $source,
                (int) $quote->amount
            );

            $payment = ServiceRequestWalletPayment::query()
                ->create([
                    'uuid' => (string) Str::uuid(),
                    'service_request_id' =>
                        $request->getKey(),
                    'service_request_quote_id' =>
                        $quote->getKey(),
                    'source_wallet_id' =>
                        $source->getKey(),
                    'provider_wallet_id' =>
                        $providerWallet->getKey(),
                    'platform_wallet_id' =>
                        $platformWallet->getKey(),
                    'payer_source' => $payerSource,
                    'amount' => (int) $quote->amount,
                    'provider_amount' =>
                        (int) $quote->provider_amount,
                    'commission_amount' =>
                        (int) $quote->commission_amount,
                    'status' =>
                        ServiceRequestWalletPaymentStatus::Locked,
                    'locked_at' => now(),
                ]);

            $quote->update([
                'status' =>
                    ServiceRequestQuoteStatus::Accepted,
                'accepted_by' =>
                    $requester->getKey(),
                'accepted_at' => now(),
            ]);

            ServiceRequestQuote::query()
                ->where(
                    'service_request_id',
                    $request->getKey()
                )
                ->where('id', '!=', $quote->getKey())
                ->where(
                    'status',
                    ServiceRequestQuoteStatus::Pending->value
                )
                ->update([
                    'status' =>
                        ServiceRequestQuoteStatus::Rejected->value,
                ]);

            if (
                $request->status
                === ServiceRequestStatus::Open
            ) {
                $request->update([
                    'status' =>
                        ServiceRequestStatus::Assigned,
                    'assigned_at' =>
                        $request->assigned_at ?? now(),
                ]);
            }

            return $payment->refresh();
        }, 3);
    }

    public function start(
        ServiceRequest $request
    ): ServiceRequest {
        $this->assertLockedPayment($request);

        if (! in_array(
            $request->status,
            [
                ServiceRequestStatus::Assigned,
                ServiceRequestStatus::Open,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'status' =>
                    'Service request cannot be started in its current status.',
            ]);
        }

        $request->update([
            'status' => ServiceRequestStatus::InProgress,
        ]);

        return $request->refresh();
    }

    public function finish(
        ServiceRequest $request
    ): ServiceRequest {
        $this->assertLockedPayment($request);

        if (
            $request->status
            !== ServiceRequestStatus::InProgress
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Only an in-progress service request can be submitted for confirmation.',
            ]);
        }

        $request->update([
            'status' =>
                ServiceRequestStatus::AwaitingConfirmation,
        ]);

        return $request->refresh();
    }

    public function confirmCompletion(
        ServiceRequest $request,
        User $actor
    ): ServiceRequestWalletPayment {
        return DB::transaction(function () use (
            $request,
            $actor
        ): ServiceRequestWalletPayment {
            $request = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            $payment = ServiceRequestWalletPayment::query()
                ->with([
                    'sourceWallet',
                    'providerWallet',
                    'platformWallet',
                ])
                ->where(
                    'service_request_id',
                    $request->getKey()
                )
                ->latest('id')
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $payment->status
                === ServiceRequestWalletPaymentStatus::Settled
            ) {
                return $payment;
            }

            if (
                $payment->status
                !== ServiceRequestWalletPaymentStatus::Locked
            ) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'Service payment is not locked for settlement.',
                ]);
            }

            if (
                $request->status
                !== ServiceRequestStatus::AwaitingConfirmation
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Service request must be awaiting confirmation before settlement.',
                ]);
            }

            $providerTransfer = null;
            $commissionTransfer = null;

            if ((int) $payment->provider_amount > 0) {
                $providerTransfer =
                    $this->wallets->transferLocked(
                        $payment->sourceWallet,
                        $payment->providerWallet,
                        (int) $payment->provider_amount,
                        WalletTransferType::ServiceProviderPayment,
                        sprintf(
                            'service-request:%d:provider-settlement',
                            $request->getKey()
                        ),
                        $request,
                        $actor,
                        'Service provider settlement'
                    );
            }

            if ((int) $payment->commission_amount > 0) {
                $commissionTransfer =
                    $this->wallets->transferLocked(
                        $payment->sourceWallet,
                        $payment->platformWallet,
                        (int) $payment->commission_amount,
                        WalletTransferType::PlatformCommission,
                        sprintf(
                            'service-request:%d:platform-commission',
                            $request->getKey()
                        ),
                        $request,
                        $actor,
                        'Service marketplace platform commission'
                    );
            }

            $payment->update([
                'status' =>
                    ServiceRequestWalletPaymentStatus::Settled,
                'provider_transfer_id' =>
                    $providerTransfer?->getKey(),
                'commission_transfer_id' =>
                    $commissionTransfer?->getKey(),
                'settled_at' => now(),
            ]);

            $request->update([
                'status' => ServiceRequestStatus::Completed,
                'completed_at' => now(),
            ]);

            return $payment->refresh();
        }, 3);
    }

    public function cancel(
        ServiceRequest $request
    ): ServiceRequest {
        return DB::transaction(function () use (
            $request
        ): ServiceRequest {
            $request = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if (
                $request->status
                === ServiceRequestStatus::Completed
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'A completed service request cannot be cancelled.',
                ]);
            }

            $payment = ServiceRequestWalletPayment::query()
                ->with('sourceWallet')
                ->where(
                    'service_request_id',
                    $request->getKey()
                )
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (
                $payment
                && $payment->status
                    === ServiceRequestWalletPaymentStatus::Locked
            ) {
                $this->wallets->unlockFunds(
                    $payment->sourceWallet,
                    (int) $payment->amount
                );

                $payment->update([
                    'status' =>
                        ServiceRequestWalletPaymentStatus::Released,
                    'released_at' => now(),
                ]);

                $payment->quote()->update([
                    'status' =>
                        ServiceRequestQuoteStatus::Cancelled,
                ]);
            }

            $request->update([
                'status' =>
                    ServiceRequestStatus::Cancelled,
            ]);

            return $request->refresh();
        }, 3);
    }

    private function resolveSourceWallet(
        ServiceRequest $request,
        User $requester,
        ServiceRequestPayerSource $payerSource,
        $setting
    ): Wallet {
        $currency = strtoupper(
            $request->building->currency ?: 'IRR'
        );

        if (
            $payerSource
            === ServiceRequestPayerSource::UserWallet
        ) {
            if (! $setting->allow_user_wallet) {
                throw ValidationException::withMessages([
                    'payer_source' =>
                        'User Wallet is disabled for building services.',
                ]);
            }

            return $this->wallets->walletFor(
                $requester,
                $currency
            );
        }

        if (! $setting->allow_unit_wallet) {
            throw ValidationException::withMessages([
                'payer_source' =>
                    'Unit Wallet is disabled for building services.',
            ]);
        }

        if (! $request->unit) {
            throw ValidationException::withMessages([
                'unit_id' =>
                    'A Unit is required when Unit Wallet is selected.',
            ]);
        }

        if (
            ! $this->residentAccess->allows(
                $requester,
                $request->unit
            )
        ) {
            throw ValidationException::withMessages([
                'unit_id' =>
                    'Requester does not have active access to the selected Unit Wallet.',
            ]);
        }

        return $this->wallets->walletFor(
            $request->unit,
            $currency
        );
    }

    private function assertLockedPayment(
        ServiceRequest $request
    ): ServiceRequestWalletPayment {
        $payment = ServiceRequestWalletPayment::query()
            ->where(
                'service_request_id',
                $request->getKey()
            )
            ->latest('id')
            ->first();

        if (
            ! $payment
            || $payment->status
                !== ServiceRequestWalletPaymentStatus::Locked
        ) {
            throw ValidationException::withMessages([
                'payment' =>
                    'Service request does not have an authorized locked payment.',
            ]);
        }

        return $payment;
    }
}
