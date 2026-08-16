<?php

namespace App\Services\Web;

use App\Enums\GuestVisitStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceRequestWalletPaymentStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\WalletPayoutStatus;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\GuestVisit;
use App\Models\ProviderBankAccount;
use App\Models\ProviderPayoutRequest;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWalletPayment;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\UnitInvoice;
use App\Models\Wallet;
use App\Models\User;
use App\Support\Jalali\JalaliDateFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PortalDashboardService
{
    public function __construct(
        private readonly PortalAccessService $access,
        private readonly ManagementHeaderContextService $header,
        private readonly JalaliDateFormatter $jalali
    ) {
    }

    public function resident(User $user): array
    {
        $units =
            $this->access
                ->residentUnits(
                    $user
                );

        $unitIds =
            $units
                ->pluck('id')
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->values();

        $buildingIds =
            $units
                ->map(
                    fn ($unit) =>
                        $unit
                            ->floor
                            ?->block
                            ?->building
                            ?->getKey()
                )
                ->filter()
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->unique()
                ->values();

        $unitCards =
            $units
                ->map(
                    function ($unit) use ($user): array {
                        $building =
                            $unit
                                ->floor
                                ?->block
                                ?->building;

                        $currency =
                            strtoupper(
                                $building
                                    ?->currency
                                ?: 'IRR'
                            );

                        $wallet =
                            $unit
                                ->wallets
                                ->first(
                                    fn ($wallet): bool =>
                                        $wallet->is_active
                                        && strtoupper(
                                            (string) $wallet->currency
                                        ) === $currency
                                );

                        return [
                            'id' =>
                                $unit->getKey(),

                            'title' =>
                                $unit->title
                                ?: 'واحد '
                                    . $unit
                                        ->unit_number,

                            'unit_number' =>
                                $unit
                                    ->unit_number,

                            'area' =>
                                (float) $unit->area,

                            'building_id' =>
                                $building
                                    ?->getKey(),

                            'building_title' =>
                                $building
                                    ?->title,

                            'complex_title' =>
                                $building
                                    ?->complex
                                    ?->title,

                            'block_title' =>
                                $unit
                                    ->floor
                                    ?->block
                                    ?->title,

                            'floor_title' =>
                                $unit
                                    ->floor
                                    ?->title,

                            'relationship' =>
                                $this->access
                                    ->residentRelationship(
                                        $user,
                                        $unit
                                    ),

                            'wallet' =>
                                $this->walletSnapshot(
                                    $wallet,
                                    $currency
                                ),
                        ];
                    }
                )
                ->values();

        $invoiceBase =
            UnitInvoice::query()
                ->whereIn(
                    'unit_id',
                    $unitIds
                );

        $outstandingTotal =
            (int) (
                (clone $invoiceBase)
                    ->whereIn(
                        'status',
                        [
                            InvoiceStatus::Issued->value,
                            InvoiceStatus::Partial->value,
                            InvoiceStatus::Overdue->value,
                        ]
                    )
                    ->sum(
                        'outstanding_amount'
                    )
            );

        // Invoice rows are loaded by the server-side Yajra endpoint.
        $invoices = collect();

        $reservations =
            FacilityReservation::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->with([
                    'buildingFacility:id,building_id,title,default_price,requires_payment,requires_approval',
                    'unit:id,unit_number,title',
                ])
                ->latest('id')
                ->limit(8)
                ->get();

        $guestVisits =
            GuestVisit::query()
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->with([
                    'guest:id,first_name,last_name,mobile,vehicle_plate',
                    'unit:id,unit_number,title',
                ])
                ->latest('id')
                ->limit(8)
                ->get();

        $services =
            ServiceRequest::query()
                ->where(
                    'requested_by',
                    $user->getKey()
                )
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->with([
                    'unit:id,unit_number,title',
                    'building:id,title',
                    'assignedTo:id,first_name,last_name,mobile',
                    'quotes' => fn ($query) =>
                        $query
                            ->with(
                                'provider:id,first_name,last_name,mobile'
                            )
                            ->latest('id')
                            ->limit(3),
                    'walletPayment',
                ])
                ->latest('id')
                ->limit(8)
                ->get();

        $tickets =
            SupportTicket::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->whereIn(
                    'unit_id',
                    $unitIds
                )
                ->with([
                    'unit:id,unit_number,title',
                    'building:id,title',
                    'supportCategory:id,title',
                    'assignedTo:id,first_name,last_name',
                ])
                ->latest('id')
                ->limit(8)
                ->get();

        $facilities =
            BuildingFacility::query()
                ->whereIn(
                    'building_id',
                    $buildingIds
                )
                ->where(
                    'is_active',
                    true
                )
                ->with([
                    'building:id,title',
                    'facilitySchedules' =>
                        fn ($query) =>
                            $query
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->with([
                                    'facilityTimeSlots' =>
                                        fn ($query) =>
                                            $query
                                                ->where(
                                                    'is_active',
                                                    true
                                                )
                                                ->orderBy(
                                                    'start_time'
                                                ),
                                ]),
                ])
                ->orderBy('title')
                ->limit(20)
                ->get();

        $categories =
            SupportCategory::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('title')
                ->get([
                    'id',
                    'title',
                ]);

        return [
            'area' =>
                'resident',

            'header' =>
                $this->header
                    ->context(
                        $user
                    ),

            'personal_wallet' =>
                $this->walletSnapshot(
                    $user
                        ->wallets()
                        ->where(
                            'currency',
                            'IRR'
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->first(),
                    'IRR'
                ),

            'units' =>
                $unitCards
                    ->all(),

            'facilities' =>
                $facilities,

            'support_categories' =>
                $categories,

            'stats' => [
                'units' =>
                    $units->count(),

                'unit_wallet_available' =>
                    $unitCards
                        ->sum(
                            fn (
                                array $unit
                            ): int =>
                                (int) data_get(
                                    $unit,
                                    'wallet.available_balance',
                                    0
                                )
                        ),

                'outstanding_total' =>
                    $outstandingTotal,

                'active_reservations' =>
                    FacilityReservation::query()
                        ->where(
                            'user_id',
                            $user->getKey()
                        )
                        ->whereIn(
                            'unit_id',
                            $unitIds
                        )
                        ->whereIn(
                            'status',
                            [
                                ReservationStatus::Pending->value,
                                ReservationStatus::PaymentPending->value,
                                ReservationStatus::Approved->value,
                                ReservationStatus::Confirmed->value,
                            ]
                        )
                        ->count(),

                'active_guests' =>
                    GuestVisit::query()
                        ->whereIn(
                            'unit_id',
                            $unitIds
                        )
                        ->whereIn(
                            'status',
                            [
                                GuestVisitStatus::Invited->value,
                                GuestVisitStatus::Entered->value,
                            ]
                        )
                        ->count(),

                'active_services' =>
                    ServiceRequest::query()
                        ->where(
                            'requested_by',
                            $user->getKey()
                        )
                        ->whereIn(
                            'unit_id',
                            $unitIds
                        )
                        ->whereIn(
                            'status',
                            [
                                ServiceRequestStatus::Open->value,
                                ServiceRequestStatus::Assigned->value,
                                ServiceRequestStatus::InProgress->value,
                                ServiceRequestStatus::AwaitingConfirmation->value,
                            ]
                        )
                        ->count(),

                'active_tickets' =>
                    SupportTicket::query()
                        ->where(
                            'user_id',
                            $user->getKey()
                        )
                        ->whereIn(
                            'unit_id',
                            $unitIds
                        )
                        ->whereIn(
                            'status',
                            [
                                SupportTicketStatus::Open->value,
                                SupportTicketStatus::Assigned->value,
                                SupportTicketStatus::InProgress->value,
                                SupportTicketStatus::WaitingUser->value,
                            ]
                        )
                        ->count(),
            ],

            'invoices' =>
                $invoices,

            'reservations' =>
                $reservations,

            'guest_visits' =>
                $guestVisits,

            'service_requests' =>
                $services,

            'support_tickets' =>
                $tickets,

            'payment_gateway' => [
                'default' =>
                    (string) config(
                        'payment_gateways.default',
                        'generic'
                    ),

                'enabled' =>
                    (bool) data_get(
                        config(
                            'payment_gateways.gateways',
                            []
                        ),
                        config(
                            'payment_gateways.default',
                            'generic'
                        ) . '.enabled',
                        false
                    ),
            ],

            'generated_at_jalali' =>
                $this->jalali
                    ->dateTime(
                        now()
                    ),
        ];
    }

    private function walletSnapshot(
        ?Wallet $wallet,
        string $currency = 'IRR'
    ): array {
        if (! $wallet) {
            return [
                'id' => null,
                'uuid' => null,
                'balance' => 0,
                'locked_balance' => 0,
                'available_balance' => 0,
                'currency' => strtoupper($currency),
                'entries' => [],
            ];
        }

        $entries =
            $wallet
                ->entries()
                ->with('transfer')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(
                    function ($entry): array {
                        $transfer =
                            $entry->transfer;

                        return [
                            'id' =>
                                $entry->getKey(),

                            'entry_type' =>
                                is_object(
                                    $entry->entry_type
                                )
                                    ? $entry
                                        ->entry_type
                                        ->value
                                    : $entry
                                        ->entry_type,

                            'amount' =>
                                (int) $entry->amount,

                            'balance_after' =>
                                (int) $entry
                                    ->balance_after,

                            'description' =>
                                $transfer
                                    ?->description,

                            'transfer_type' =>
                                $transfer
                                    ? (
                                        is_object(
                                            $transfer->type
                                        )
                                            ? $transfer
                                                ->type
                                                ->value
                                            : $transfer
                                                ->type
                                    )
                                    : null,

                            'completed_at' =>
                                $transfer
                                    ?->completed_at
                                    ?->toISOString(),

                            'created_at' =>
                                $entry
                                    ->created_at
                                    ?->toISOString(),

                            'created_at_jalali' =>
                                $this->jalali
                                    ->dateTime(
                                        $entry
                                            ->created_at
                                    ),
                        ];
                    }
                )
                ->values()
                ->all();

        return [
            'id' =>
                $wallet->getKey(),

            'uuid' =>
                $wallet->uuid,

            'balance' =>
                (int) $wallet->balance,

            'locked_balance' =>
                (int) $wallet
                    ->locked_balance,

            'available_balance' =>
                $wallet
                    ->availableBalance(),

            'currency' =>
                strtoupper(
                    $wallet->currency
                    ?: $currency
                ),

            'entries' =>
                $entries,
        ];
    }

    public function provider(User $user): array
    {
        $services =
            ServiceRequest::query()
                ->where(
                    'assigned_to',
                    $user->getKey()
                )
                ->with([
                    'building:id,title',
                    'unit:id,unit_number,title',
                    'requestedBy:id,first_name,last_name,mobile',
                    'quotes' =>
                        fn ($query) =>
                            $query
                                ->where(
                                    'provider_user_id',
                                    $user->getKey()
                                )
                                ->latest('id'),
                    'walletPayment',
                ])
                ->latest('id')
                ->limit(20)
                ->get();

        $payouts =
            ProviderPayoutRequest::query()
                ->where(
                    'provider_user_id',
                    $user->getKey()
                )
                ->with(
                    'bankAccount'
                )
                ->latest('id')
                ->limit(10)
                ->get();

        $bankAccounts =
            ProviderBankAccount::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->orderByDesc(
                    'is_default'
                )
                ->latest('id')
                ->get();

        $settledEarnings =
            (int) ServiceRequestWalletPayment::query()
                ->where(
                    'status',
                    ServiceRequestWalletPaymentStatus::Settled->value
                )
                ->whereHas(
                    'serviceRequest',
                    fn (
                        Builder $query
                    ) =>
                        $query->where(
                            'assigned_to',
                            $user->getKey()
                        )
                )
                ->sum(
                    'provider_amount'
                );

        $pendingPayout =
            (int) ProviderPayoutRequest::query()
                ->where(
                    'provider_user_id',
                    $user->getKey()
                )
                ->whereIn(
                    'status',
                    [
                        WalletPayoutStatus::Pending->value,
                        WalletPayoutStatus::Approved->value,
                    ]
                )
                ->sum(
                    'amount'
                );

        return [
            'area' =>
                'provider',

            'header' =>
                $this->header
                    ->context(
                        $user
                    ),

            'personal_wallet' =>
                $this->walletSnapshot(
                    $user
                        ->wallets()
                        ->where(
                            'currency',
                            'IRR'
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->first(),
                    'IRR'
                ),

            'stats' => [
                'active_jobs' =>
                    ServiceRequest::query()
                        ->where(
                            'assigned_to',
                            $user->getKey()
                        )
                        ->whereIn(
                            'status',
                            [
                                ServiceRequestStatus::Assigned->value,
                                ServiceRequestStatus::InProgress->value,
                                ServiceRequestStatus::AwaitingConfirmation->value,
                            ]
                        )
                        ->count(),

                'completed_jobs' =>
                    ServiceRequest::query()
                        ->where(
                            'assigned_to',
                            $user->getKey()
                        )
                        ->where(
                            'status',
                            ServiceRequestStatus::Completed->value
                        )
                        ->count(),

                'settled_earnings' =>
                    $settledEarnings,

                'pending_payout' =>
                    $pendingPayout,

                'verified_bank_accounts' =>
                    $bankAccounts
                        ->where(
                            'is_verified',
                            true
                        )
                        ->count(),
            ],

            'service_requests' =>
                $services,

            'payouts' =>
                $payouts,

            'bank_accounts' =>
                $bankAccounts,

            'generated_at_jalali' =>
                $this->jalali
                    ->dateTime(
                        now()
                    ),
        ];
    }
}
