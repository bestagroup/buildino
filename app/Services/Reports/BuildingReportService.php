<?php

namespace App\Services\Reports;

use App\Enums\BuildingBillPaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestWalletPaymentStatus;
use App\Enums\WalletPayoutStatus;
use App\Enums\WalletTransferStatus;
use App\Enums\WalletTransferType;
use App\Models\Building;
use App\Models\BuildingBillPayment;
use App\Models\BuildingExpense;
use App\Models\FacilityReservation;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWalletPayment;
use App\Models\UnitInvoice;
use App\Models\Wallet;
use App\Models\WalletPayoutRequest;
use App\Models\WalletTransfer;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BuildingReportService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ReportPeriodResolver $periods
    ) {
    }


    public function managementDashboard(
        Building $building,
        ?string $from = null,
        ?string $to = null
    ): array {
        $financial = $this->financialSummary(
            $building,
            $from,
            $to
        );

        $receivables = $this->receivables(
            $building,
            $to
        );

        $facilities = $this->facilities(
            $building,
            $from,
            $to
        );

        $services = $this->services(
            $building,
            $from,
            $to
        );

        return [
            'building_id' =>
                $building->getKey(),

            'period' =>
                $financial['period'],

            'kpis' => [
                'wallet_balance' =>
                    $financial['wallet']['balance'],

                'wallet_available_balance' =>
                    $financial['wallet']['available_balance'],

                'receivables_outstanding' =>
                    $receivables['totals']['outstanding_amount'],

                'receivables_over_90_days' =>
                    $receivables['aging']['days_90_plus'],

                'cash_inflow' =>
                    $financial['cash_flow']['inflow'],

                'cash_outflow' =>
                    $financial['cash_flow']['outflow'],

                'net_cash_flow' =>
                    $financial['cash_flow']['net'],

                'charge_collections' =>
                    $financial['cash_flow']['charge_collections'],

                'facility_paid_amount' =>
                    $facilities['totals']['paid_amount'],

                'service_gmv' =>
                    $services['marketplace']['gmv'],

                'service_platform_commission' =>
                    $services['marketplace']['platform_commission'],
            ],

            'receivables_aging' =>
                $receivables['aging'],

            'facility_summary' =>
                $facilities['totals'],

            'service_summary' =>
                $services['marketplace'],

            'generated_at' =>
                now()->toISOString(),
        ];
    }

    public function financialSummary(
        Building $building,
        ?string $from = null,
        ?string $to = null
    ): array {
        $period = $this->periods->resolve(
            $from,
            $to
        );

        $wallet = $this->buildingWallet(
            $building
        );

        $invoiceBase = UnitInvoice::query()
            ->where(
                'building_id',
                $building->getKey()
            )
            ->whereBetween(
                'issue_date',
                [
                    $period['from']->toDateString(),
                    $period['to']->toDateString(),
                ]
            )
            ->whereNotIn(
                'status',
                [
                    InvoiceStatus::Draft->value,
                    InvoiceStatus::Cancelled->value,
                    InvoiceStatus::Void->value,
                ]
            );

        $periodInvoiced =
            (int) (clone $invoiceBase)
                ->sum('total_amount');

        $periodInvoicePaid =
            (int) (clone $invoiceBase)
                ->sum('paid_amount');

        $currentReceivables =
            (int) UnitInvoice::query()
                ->where(
                    'building_id',
                    $building->getKey()
                )
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->whereIn(
                    'status',
                    [
                        InvoiceStatus::Issued->value,
                        InvoiceStatus::Partial->value,
                        InvoiceStatus::Overdue->value,
                    ]
                )
                ->sum('outstanding_amount');

        $currentOverdue =
            (int) UnitInvoice::query()
                ->where(
                    'building_id',
                    $building->getKey()
                )
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->whereDate(
                    'due_date',
                    '<',
                    CarbonImmutable::now()
                        ->toDateString()
                )
                ->whereIn(
                    'status',
                    [
                        InvoiceStatus::Issued->value,
                        InvoiceStatus::Partial->value,
                        InvoiceStatus::Overdue->value,
                    ]
                )
                ->sum('outstanding_amount');

        $transfers = $this->periodTransfers(
            $wallet,
            $period['from'],
            $period['to']
        );

        $inflow = (int) $transfers
            ->where(
                'destination_wallet_id',
                $wallet->getKey()
            )
            ->sum('amount');

        $outflow = (int) $transfers
            ->where(
                'source_wallet_id',
                $wallet->getKey()
            )
            ->sum('amount');

        $chargeCollections = $this->sumType(
            $transfers,
            WalletTransferType::ChargeCollection,
            'destination_wallet_id',
            $wallet->getKey()
        );

        $facilityIncome = $this->sumType(
            $transfers,
            WalletTransferType::FacilityFee,
            'destination_wallet_id',
            $wallet->getKey()
        );

        $refunds = $this->sumType(
            $transfers,
            WalletTransferType::Refund,
            'source_wallet_id',
            $wallet->getKey()
        );

        $billPayments = $this->sumType(
            $transfers,
            WalletTransferType::BillPayment,
            'source_wallet_id',
            $wallet->getKey()
        );

        $buildingPayouts = $this->sumType(
            $transfers,
            WalletTransferType::Payout,
            'source_wallet_id',
            $wallet->getKey()
        );

        $postedExpenses =
            (int) BuildingExpense::query()
                ->where(
                    'building_id',
                    $building->getKey()
                )
                ->whereNotNull('posted_at')
                ->whereBetween(
                    'expense_date',
                    [
                        $period['from']->toDateString(),
                        $period['to']->toDateString(),
                    ]
                )
                ->sum('amount');

        $paidBills =
            (int) BuildingBillPayment::query()
                ->where(
                    'building_id',
                    $building->getKey()
                )
                ->where(
                    'status',
                    BuildingBillPaymentStatus::Paid->value
                )
                ->whereBetween(
                    'completed_at',
                    [
                        $period['from'],
                        $period['to'],
                    ]
                )
                ->sum('amount');

        $paidPayouts =
            (int) WalletPayoutRequest::query()
                ->where(
                    'building_id',
                    $building->getKey()
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
                ->sum('amount');

        return [
            'building_id' => $building->getKey(),

            'period' => $this->periodData(
                $period
            ),

            'wallet' => [
                'wallet_id' => $wallet->getKey(),
                'currency' => $wallet->currency,
                'balance' =>
                    (int) $wallet->balance,
                'locked_balance' =>
                    (int) $wallet->locked_balance,
                'available_balance' =>
                    $wallet->availableBalance(),
            ],

            'receivables' => [
                'period_invoiced' =>
                    $periodInvoiced,
                'current_paid_on_period_invoices' =>
                    $periodInvoicePaid,
                'current_outstanding' =>
                    $currentReceivables,
                'current_overdue' =>
                    $currentOverdue,
                'current_paid_ratio_on_period_invoices' =>
                    $periodInvoiced > 0
                        ? round(
                            $periodInvoicePaid
                            / $periodInvoiced,
                            4
                        )
                        : 0.0,
            ],

            'cash_flow' => [
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => $inflow - $outflow,

                'charge_collections' =>
                    $chargeCollections,

                'facility_income' =>
                    $facilityIncome,

                'refunds' => $refunds,

                'bill_payments' =>
                    $billPayments,

                'building_payouts' =>
                    $buildingPayouts,
            ],

            'operations' => [
                'posted_expenses' =>
                    $postedExpenses,

                /*
                 * These operational tables are exposed separately from
                 * Wallet cash flow so accounting and cash movement are
                 * not accidentally treated as the same metric.
                 */
                'paid_building_bills' =>
                    $paidBills,

                'paid_building_payouts' =>
                    $paidPayouts,
            ],

            'generated_at' =>
                now()->toISOString(),
        ];
    }

    public function receivables(
        Building $building,
        ?string $asOf = null
    ): array {
        $date = $asOf
            ? CarbonImmutable::parse(
                $asOf
            )->endOfDay()
            : CarbonImmutable::now()
                ->endOfDay();

        $invoices = UnitInvoice::query()
            ->with([
                'unit:id,unit_number',
            ])
            ->where(
                'building_id',
                $building->getKey()
            )
            ->where(
                'outstanding_amount',
                '>',
                0
            )
            ->whereDate(
                'issue_date',
                '<=',
                $date->toDateString()
            )
            ->whereIn(
                'status',
                [
                    InvoiceStatus::Issued->value,
                    InvoiceStatus::Partial->value,
                    InvoiceStatus::Overdue->value,
                ]
            )
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $buckets = [
            'not_due' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_90_plus' => 0,
        ];

        $units = [];

        foreach ($invoices as $invoice) {
            $amount =
                (int) $invoice->outstanding_amount;

            $bucket = $this->agingBucket(
                $invoice->due_date
                    ? CarbonImmutable::parse(
                        $invoice->due_date
                    )
                    : null,
                $date
            );

            $buckets[$bucket] += $amount;

            $unitId = (int) $invoice->unit_id;

            $units[$unitId] ??= [
                'unit_id' => $unitId,
                'unit_number' =>
                    $invoice->unit?->unit_number,
                'invoice_count' => 0,
                'outstanding_amount' => 0,
                'oldest_due_date' => null,
            ];

            $units[$unitId]['invoice_count']++;

            $units[$unitId]['outstanding_amount']
                += $amount;

            $due = $invoice->due_date
                ? $invoice->due_date
                    ->toDateString()
                : null;

            if (
                $due !== null
                && (
                    $units[$unitId]['oldest_due_date']
                        === null
                    || $due
                        < $units[$unitId]['oldest_due_date']
                )
            ) {
                $units[$unitId]['oldest_due_date']
                    = $due;
            }
        }

        $units = collect(
            array_values($units)
        )
            ->sortByDesc(
                'outstanding_amount'
            )
            ->values()
            ->all();

        return [
            'building_id' =>
                $building->getKey(),

            'as_of' => $date->toDateString(),

            'totals' => [
                'invoice_count' =>
                    $invoices->count(),

                'unit_count' =>
                    count($units),

                'outstanding_amount' =>
                    (int) $invoices->sum(
                        'outstanding_amount'
                    ),
            ],

            'aging' => $buckets,

            'units' => $units,

            'generated_at' =>
                now()->toISOString(),
        ];
    }

    public function cashFlow(
        Building $building,
        ?string $from = null,
        ?string $to = null,
        string $granularity = 'day'
    ): array {
        $period = $this->periods->resolve(
            $from,
            $to
        );

        $wallet = $this->buildingWallet(
            $building
        );

        $transfers = $this->periodTransfers(
            $wallet,
            $period['from'],
            $period['to']
        );

        $series = [];

        foreach ($transfers as $transfer) {
            $date = CarbonImmutable::parse(
                $transfer->completed_at
            );

            $key = $granularity === 'month'
                ? $date->format('Y-m')
                : $date->format('Y-m-d');

            $series[$key] ??= [
                'period' => $key,
                'inflow' => 0,
                'outflow' => 0,
                'net' => 0,
            ];

            if (
                (int) $transfer
                    ->destination_wallet_id
                === (int) $wallet->getKey()
            ) {
                $series[$key]['inflow']
                    += (int) $transfer->amount;
            }

            if (
                (int) $transfer
                    ->source_wallet_id
                === (int) $wallet->getKey()
            ) {
                $series[$key]['outflow']
                    += (int) $transfer->amount;
            }

            $series[$key]['net'] =
                $series[$key]['inflow']
                - $series[$key]['outflow'];
        }

        ksort($series);

        $breakdown = [];

        foreach (
            WalletTransferType::cases()
            as $type
        ) {
            $typeTransfers = $transfers
                ->filter(
                    fn (WalletTransfer $transfer) =>
                        $transfer->type === $type
                );

            $breakdown[$type->value] = [
                'inflow' =>
                    (int) $typeTransfers
                        ->where(
                            'destination_wallet_id',
                            $wallet->getKey()
                        )
                        ->sum('amount'),

                'outflow' =>
                    (int) $typeTransfers
                        ->where(
                            'source_wallet_id',
                            $wallet->getKey()
                        )
                        ->sum('amount'),
            ];
        }

        return [
            'building_id' =>
                $building->getKey(),

            'wallet_id' =>
                $wallet->getKey(),

            'currency' =>
                $wallet->currency,

            'period' =>
                $this->periodData(
                    $period
                ),

            'granularity' =>
                $granularity,

            'totals' => [
                'inflow' =>
                    (int) $transfers
                        ->where(
                            'destination_wallet_id',
                            $wallet->getKey()
                        )
                        ->sum('amount'),

                'outflow' =>
                    (int) $transfers
                        ->where(
                            'source_wallet_id',
                            $wallet->getKey()
                        )
                        ->sum('amount'),

                'net' =>
                    (int) $transfers
                        ->where(
                            'destination_wallet_id',
                            $wallet->getKey()
                        )
                        ->sum('amount')
                    - (int) $transfers
                        ->where(
                            'source_wallet_id',
                            $wallet->getKey()
                        )
                        ->sum('amount'),
            ],

            'series' =>
                array_values($series),

            'by_transfer_type' =>
                $breakdown,

            'generated_at' =>
                now()->toISOString(),
        ];
    }

    public function facilities(
        Building $building,
        ?string $from = null,
        ?string $to = null
    ): array {
        $period = $this->periods->resolve(
            $from,
            $to
        );

        $reservations = FacilityReservation::query()
            ->with([
                'buildingFacility:id,building_id,title,code',
                'walletPayment',
            ])
            ->whereHas(
                'buildingFacility',
                fn ($query) =>
                    $query->where(
                        'building_id',
                        $building->getKey()
                    )
            )
            /*
             * reservation_date is cast as a date on FacilityReservation.
             * Use whereDate() rather than a string whereBetween() so the
             * report behaves identically on MySQL and SQLite. SQLite can
             * persist cast date values with a time component, which can
             * otherwise exclude same-day reservations.
             */
            ->whereDate(
                'reservation_date',
                '>=',
                $period['from']->toDateString()
            )
            ->whereDate(
                'reservation_date',
                '<=',
                $period['to']->toDateString()
            )
            ->get();

        $items = [];

        foreach (
            $reservations->groupBy(
                'building_facility_id'
            )
            as $facilityId => $group
        ) {
            $facility = $group
                ->first()
                ->buildingFacility;

            $items[] = [
                'facility_id' =>
                    (int) $facilityId,

                'title' =>
                    $facility?->title,

                'code' =>
                    $facility?->code,

                'reservation_count' =>
                    $group->count(),

                'approved_or_confirmed_count' =>
                    $group->filter(
                        fn (FacilityReservation $r) =>
                            in_array(
                                $r->status,
                                [
                                    ReservationStatus::Approved,
                                    ReservationStatus::Confirmed,
                                    ReservationStatus::Completed,
                                ],
                                true
                            )
                    )->count(),

                'cancelled_count' =>
                    $group->where(
                        'status',
                        ReservationStatus::Cancelled
                    )->count(),

                'priced_amount' =>
                    (int) $group->sum(
                        'final_amount'
                    ),

                'paid_amount' =>
                    (int) $group->sum(
                        fn (FacilityReservation $r) =>
                            (int) (
                                $r->walletPayment?->amount
                                ?? 0
                            )
                    ),
            ];
        }

        usort(
            $items,
            fn (array $a, array $b) =>
                $b['paid_amount']
                <=> $a['paid_amount']
        );

        return [
            'building_id' =>
                $building->getKey(),

            'period' =>
                $this->periodData(
                    $period
                ),

            'totals' => [
                'reservation_count' =>
                    $reservations->count(),

                'priced_amount' =>
                    (int) $reservations->sum(
                        'final_amount'
                    ),

                'paid_amount' =>
                    (int) $reservations->sum(
                        fn (FacilityReservation $r) =>
                            (int) (
                                $r->walletPayment?->amount
                                ?? 0
                            )
                    ),
            ],

            'facilities' => $items,

            'generated_at' =>
                now()->toISOString(),
        ];
    }

    public function services(
        Building $building,
        ?string $from = null,
        ?string $to = null
    ): array {
        $period = $this->periods->resolve(
            $from,
            $to
        );

        $settled =
            ServiceRequestWalletPayment::query()
                ->with([
                    'serviceRequest:id,building_id,type,status,completed_at',
                ])
                ->whereHas(
                    'serviceRequest',
                    fn ($query) =>
                        $query->where(
                            'building_id',
                            $building->getKey()
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

        $currentLocked =
            ServiceRequestWalletPayment::query()
                ->whereHas(
                    'serviceRequest',
                    fn ($query) =>
                        $query->where(
                            'building_id',
                            $building->getKey()
                        )
                )
                ->where(
                    'status',
                    ServiceRequestWalletPaymentStatus::Locked->value
                )
                ->get();

        $requestBase = ServiceRequest::query()
            ->where(
                'building_id',
                $building->getKey()
            )
            ->whereBetween(
                'created_at',
                [
                    $period['from'],
                    $period['to'],
                ]
            );

        $requestCount =
            (clone $requestBase)->count();

        $completedCount =
            (clone $requestBase)
                ->where(
                    'status',
                    ServiceRequestStatus::Completed->value
                )
                ->count();

        $byType = [];

        foreach (
            $settled->groupBy(
                fn (ServiceRequestWalletPayment $payment) =>
                    $payment
                        ->serviceRequest
                        ?->type
                    ?? 'unknown'
            )
            as $type => $group
        ) {
            $byType[] = [
                'type' => $type,
                'settled_count' =>
                    $group->count(),
                'gmv' =>
                    (int) $group->sum(
                        'amount'
                    ),
                'provider_amount' =>
                    (int) $group->sum(
                        'provider_amount'
                    ),
                'platform_commission' =>
                    (int) $group->sum(
                        'commission_amount'
                    ),
            ];
        }

        usort(
            $byType,
            fn (array $a, array $b) =>
                $b['gmv']
                <=> $a['gmv']
        );

        return [
            'building_id' =>
                $building->getKey(),

            'period' =>
                $this->periodData(
                    $period
                ),

            'requests' => [
                'created' =>
                    $requestCount,
                'completed' =>
                    $completedCount,
                'completion_ratio' =>
                    $requestCount > 0
                        ? round(
                            $completedCount
                            / $requestCount,
                            4
                        )
                        : 0.0,
            ],

            'marketplace' => [
                'current_locked_count' =>
                    $currentLocked->count(),
                'current_locked_amount' =>
                    (int) $currentLocked
                        ->sum('amount'),
                'settled_count' =>
                    $settled->count(),
                'gmv' =>
                    (int) $settled->sum(
                        'amount'
                    ),
                'provider_amount' =>
                    (int) $settled->sum(
                        'provider_amount'
                    ),
                'platform_commission' =>
                    (int) $settled->sum(
                        'commission_amount'
                    ),
            ],

            'by_service_type' =>
                $byType,

            'generated_at' =>
                now()->toISOString(),
        ];
    }

    private function buildingWallet(
        Building $building
    ): Wallet {
        return $this->wallets->walletFor(
            $building,
            strtoupper(
                $building->currency ?: 'IRR'
            )
        )->refresh();
    }

    private function periodTransfers(
        Wallet $wallet,
        CarbonImmutable $from,
        CarbonImmutable $to
    ): Collection {
        return WalletTransfer::query()
            ->where(
                'status',
                WalletTransferStatus::Completed->value
            )
            ->whereBetween(
                'completed_at',
                [
                    $from,
                    $to,
                ]
            )
            ->where(
                function ($query) use (
                    $wallet
                ): void {
                    $query
                        ->where(
                            'source_wallet_id',
                            $wallet->getKey()
                        )
                        ->orWhere(
                            'destination_wallet_id',
                            $wallet->getKey()
                        );
                }
            )
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();
    }

    private function sumType(
        Collection $transfers,
        WalletTransferType $type,
        string $walletColumn,
        int $walletId
    ): int {
        return (int) $transfers
            ->filter(
                fn (WalletTransfer $transfer) =>
                    $transfer->type === $type
                    && (int) $transfer->{$walletColumn}
                        === $walletId
            )
            ->sum('amount');
    }

    private function agingBucket(
        ?CarbonImmutable $dueDate,
        CarbonImmutable $asOf
    ): string {
        if (
            $dueDate === null
            || $dueDate->greaterThanOrEqualTo(
                $asOf->startOfDay()
            )
        ) {
            return 'not_due';
        }

        $days = $dueDate
            ->startOfDay()
            ->diffInDays(
                $asOf->startOfDay()
            );

        return match (true) {
            $days <= 30 =>
                'days_1_30',
            $days <= 60 =>
                'days_31_60',
            $days <= 90 =>
                'days_61_90',
            default =>
                'days_90_plus',
        };
    }

    private function periodData(
        array $period
    ): array {
        return [
            'from' =>
                $period['from']->toDateString(),
            'to' =>
                $period['to']->toDateString(),
        ];
    }
}
