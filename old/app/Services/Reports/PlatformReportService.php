<?php

namespace App\Services\Reports;

use App\Enums\ServiceRequestWalletPaymentStatus;
use App\Enums\WalletPayoutStatus;
use App\Enums\WalletReconciliationStatus;
use App\Models\Building;
use App\Models\PlatformWalletAccount;
use App\Models\ProviderPayoutRequest;
use App\Models\ServiceRequestWalletPayment;
use App\Models\Wallet;
use App\Models\WalletReconciliation;

final class PlatformReportService
{
    public function __construct(
        private readonly ReportPeriodResolver $periods
    ) {
    }

    public function summary(
        ?string $from = null,
        ?string $to = null,
        string $currency = 'IRR'
    ): array {
        $period = $this->periods->resolve(
            $from,
            $to
        );

        $currency = strtoupper($currency);

        $settledPayments =
            ServiceRequestWalletPayment::query()
                ->whereHas(
                    'serviceRequest.building',
                    fn ($query) =>
                        $query->where(
                            'currency',
                            $currency
                        )
                )
                ->where(
                    'status',
                    ServiceRequestWalletPaymentStatus::Settled->value
                )
                ->whereBetween(
                    'settled_at',
                    [
                        $period['from'],
                        $period['to'],
                    ]
                )
                ->get();

        $providerPayouts =
            ProviderPayoutRequest::query()
                ->whereHas(
                    'wallet',
                    fn ($query) =>
                        $query->where(
                            'currency',
                            $currency
                        )
                )
                ->where(
                    'status',
                    WalletPayoutStatus::Paid->value
                )
                ->whereBetween(
                    'paid_at',
                    [
                        $period['from'],
                        $period['to'],
                    ]
                )
                ->get();

        $platformWallets =
            Wallet::query()
                ->whereIn(
                    'owner_id',
                    PlatformWalletAccount::query()
                        ->where('currency', $currency)
                        ->select('id')
                )
                ->whereIn(
                    'owner_type',
                    array_values(
                        array_unique([
                            (new PlatformWalletAccount())
                                ->getMorphClass(),
                            PlatformWalletAccount::class,
                        ])
                    )
                )
                ->where(
                    'currency',
                    $currency
                )
                ->get();

        $platformWalletIds =
            $platformWallets
                ->pluck('id');

        $latestReconciliations =
            WalletReconciliation::query()
                ->whereIn(
                    'wallet_id',
                    $platformWalletIds
                )
                ->latest('id')
                ->get()
                ->unique('wallet_id');

        $reconciliationMismatchCount =
            $latestReconciliations
                ->filter(
                    fn (WalletReconciliation $item) =>
                        $item->status
                        === WalletReconciliationStatus::Mismatch
                )
                ->count();

        return [
            'period' => [
                'from' =>
                    $period['from']->toDateString(),
                'to' =>
                    $period['to']->toDateString(),
            ],

            'currency' => $currency,

            'buildings' => [
                'total' =>
                    Building::query()->count(),
                'active' =>
                    Building::query()
                        ->where('is_active', true)
                        ->count(),
            ],

            'service_marketplace' => [
                'settled_count' =>
                    $settledPayments->count(),

                'gmv' =>
                    (int) $settledPayments
                        ->sum('amount'),

                'provider_amount' =>
                    (int) $settledPayments
                        ->sum('provider_amount'),

                'platform_commission' =>
                    (int) $settledPayments
                        ->sum('commission_amount'),

                'effective_commission_ratio' =>
                    (int) $settledPayments
                        ->sum('amount') > 0
                        ? round(
                            (int) $settledPayments
                                ->sum('commission_amount')
                            / (int) $settledPayments
                                ->sum('amount'),
                            4
                        )
                        : 0.0,
            ],

            'provider_payouts' => [
                'paid_count' =>
                    $providerPayouts->count(),

                'gross_amount' =>
                    (int) $providerPayouts
                        ->sum('amount'),

                'net_amount' =>
                    (int) $providerPayouts
                        ->sum('net_amount'),

                'fees' =>
                    (int) $providerPayouts
                        ->sum('fee_amount'),
            ],

            'platform_wallets' => [
                'wallet_count' =>
                    $platformWallets->count(),

                'balance' =>
                    (int) $platformWallets
                        ->sum('balance'),

                'locked_balance' =>
                    (int) $platformWallets
                        ->sum('locked_balance'),

                'available_balance' =>
                    (int) $platformWallets
                        ->sum(
                            fn (Wallet $wallet) =>
                                $wallet->availableBalance()
                        ),

                'latest_reconciliation_count' =>
                    $latestReconciliations
                        ->count(),

                'reconciliation_mismatch_count' =>
                    $reconciliationMismatchCount,
            ],

            'generated_at' =>
                now()->toISOString(),
        ];
    }
}
