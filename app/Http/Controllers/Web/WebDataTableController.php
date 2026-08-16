<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FacilityReservation;
use App\Models\GuestVisit;
use App\Models\Payment;
use App\Models\ProviderPayoutRequest;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\UnitInvoice;
use App\Models\WalletEntry;
use App\Services\Web\ManagementDashboardAccessService;
use App\Services\Web\PortalAccessService;
use App\Services\Web\WebDataTablePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

final class WebDataTableController extends Controller
{
    public function management(
        Request $request,
        string $table,
        ManagementDashboardAccessService $access,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $user =
            $request->user();

        $buildings =
            $access->accessibleBuildings(
                $user
            );

        $buildingIds =
            $this->managementBuildingIds(
                $request,
                $buildings
            );

        abort_unless(
            in_array(
                $table,
                [
                    'payments',
                    'reservations',
                    'services',
                    'support',
                ],
                true
            ),
            Response::HTTP_NOT_FOUND
        );

        return match ($table) {
            'payments' =>
                $this->managementPayments(
                    $request,
                    $buildingIds,
                    $presenter
                ),

            'reservations' =>
                $this->managementReservations(
                    $request,
                    $buildingIds,
                    $presenter
                ),

            'services' =>
                $this->managementServices(
                    $request,
                    $buildingIds,
                    $presenter
                ),

            'support' =>
                $this->managementSupport(
                    $request,
                    $buildingIds,
                    $presenter
                ),
        };
    }

    public function resident(
        Request $request,
        string $table,
        PortalAccessService $access,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $user =
            $request->user();

        $unitIds =
            $access
                ->residentUnits(
                    $user
                )
                ->pluck('id')
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->values();

        abort_unless(
            in_array(
                $table,
                [
                    'invoices',
                    'reservations',
                    'guests',
                    'services',
                    'support',
                    'wallet',
                ],
                true
            ),
            Response::HTTP_NOT_FOUND
        );

        return match ($table) {
            'invoices' =>
                $this->residentInvoices(
                    $request,
                    $unitIds,
                    $presenter
                ),

            'reservations' =>
                $this->residentReservations(
                    $request,
                    $user->getKey(),
                    $unitIds,
                    $presenter
                ),

            'guests' =>
                $this->residentGuests(
                    $request,
                    $unitIds,
                    $presenter
                ),

            'services' =>
                $this->residentServices(
                    $request,
                    $user->getKey(),
                    $unitIds,
                    $presenter
                ),

            'support' =>
                $this->residentSupport(
                    $request,
                    $user->getKey(),
                    $unitIds,
                    $presenter
                ),

            'wallet' =>
                $this->residentWallet(
                    $request,
                    $user,
                    $unitIds,
                    $presenter
                ),
        };
    }

    public function provider(
        Request $request,
        string $table,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $user =
            $request->user();

        abort_unless(
            in_array(
                $table,
                [
                    'services',
                    'payouts',
                    'wallet',
                ],
                true
            ),
            Response::HTTP_NOT_FOUND
        );

        return match ($table) {
            'services' =>
                $this->providerServices(
                    $request,
                    $user->getKey(),
                    $presenter
                ),

            'payouts' =>
                $this->providerPayouts(
                    $request,
                    $user->getKey(),
                    $presenter
                ),

            'wallet' =>
                $this->providerWallet(
                    $request,
                    $user,
                    $presenter
                ),
        };
    }

    /**
     * @param Collection<int, \App\Models\Building> $buildings
     * @return Collection<int, int>
     */
    private function managementBuildingIds(
        Request $request,
        Collection $buildings
    ): Collection {
        if (
            ! $request->filled(
                'building_id'
            )
        ) {
            return $buildings
                ->pluck('id')
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->values();
        }

        $buildingId =
            $request->integer(
                'building_id'
            );

        abort_unless(
            $buildings
                ->contains(
                    fn ($building): bool =>
                        $building->getKey()
                        === $buildingId
                ),
            Response::HTTP_FORBIDDEN
        );

        return collect([
            $buildingId,
        ]);
    }

    private function managementPayments(
        Request $request,
        Collection $buildingIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            Payment::query()
                ->with([
                    'payerUser:id,first_name,last_name,mobile',
                    'building:id,title',
                ])
                ->when(
                    $buildingIds->isNotEmpty(),
                    fn (Builder $query) =>
                        $query->whereIn(
                            'building_id',
                            $buildingIds->all()
                        ),
                    fn (Builder $query) =>
                        $query->whereRaw(
                            '1 = 0'
                        )
                );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'payer_name',
                fn (Payment $payment): string =>
                    $presenter->person(
                        $payment->payerUser
                    )
            )
            ->filterColumn(
                'payer_name',
                function (
                    Builder $query,
                    string $keyword
                ): void {
                    $query->whereHas(
                        'payerUser',
                        fn (Builder $userQuery) =>
                            $userQuery
                                ->where(
                                    'first_name',
                                    'like',
                                    "%{$keyword}%"
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    "%{$keyword}%"
                                )
                                ->orWhere(
                                    'mobile',
                                    'like',
                                    "%{$keyword}%"
                                )
                    );
                }
            )
            ->addColumn(
                'amount_formatted',
                fn (Payment $payment): string =>
                    $presenter->money(
                        $payment->amount
                    )
                    . ' '
                    . $payment->currency
            )
            ->addColumn(
                'status_label',
                fn (Payment $payment): string =>
                    $presenter->statusLabel(
                        $payment->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (Payment $payment): string =>
                    $presenter->statusTone(
                        $payment->status
                    )
            )
            ->addColumn(
                'created_at_jalali',
                fn (Payment $payment): string =>
                    $presenter->dateTime(
                        $payment->created_at
                    )
            )
            ->orderColumn(
                'payment_number',
                'payment_number $1'
            )
            ->toJson();
    }

    private function managementReservations(
        Request $request,
        Collection $buildingIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            FacilityReservation::query()
                ->with([
                    'buildingFacility:id,building_id,title',
                    'user:id,first_name,last_name,mobile',
                    'unit:id,unit_number,title',
                ])
                ->whereHas(
                    'buildingFacility',
                    fn (Builder $facility) =>
                        $facility->whereIn(
                            'building_id',
                            $buildingIds->all()
                        )
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyDateColumnFilters(
            $query,
            $request,
            'reservation_date'
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'facility_title',
                fn (FacilityReservation $reservation): string =>
                    $reservation
                        ->buildingFacility
                        ?->title
                    ?: '—'
            )
            ->addColumn(
                'user_name',
                fn (FacilityReservation $reservation): string =>
                    $presenter->person(
                        $reservation->user
                    )
            )
            ->filterColumn(
                'user_name',
                function (
                    Builder $query,
                    string $keyword
                ): void {
                    $query->whereHas(
                        'user',
                        fn (Builder $userQuery) =>
                            $userQuery
                                ->where(
                                    'first_name',
                                    'like',
                                    "%{$keyword}%"
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    "%{$keyword}%"
                                )
                                ->orWhere(
                                    'mobile',
                                    'like',
                                    "%{$keyword}%"
                                )
                    );
                }
            )
            ->addColumn(
                'reservation_date_jalali',
                fn (FacilityReservation $reservation): string =>
                    $presenter->date(
                        $reservation
                            ->reservation_date
                    )
            )
            ->addColumn(
                'status_label',
                fn (FacilityReservation $reservation): string =>
                    $presenter->statusLabel(
                        $reservation->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (FacilityReservation $reservation): string =>
                    $presenter->statusTone(
                        $reservation->status
                    )
            )
            ->toJson();
    }

    private function managementServices(
        Request $request,
        Collection $buildingIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            ServiceRequest::query()
                ->with([
                    'assignedTo:id,first_name,last_name,mobile',
                    'building:id,title',
                ])
                ->whereIn(
                    'building_id',
                    $buildingIds->all()
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'assigned_name',
                fn (ServiceRequest $service): string =>
                    $presenter->person(
                        $service->assignedTo
                    )
            )
            ->addColumn(
                'status_label',
                fn (ServiceRequest $service): string =>
                    $presenter->statusLabel(
                        $service->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (ServiceRequest $service): string =>
                    $presenter->statusTone(
                        $service->status
                    )
            )
            ->addColumn(
                'created_at_jalali',
                fn (ServiceRequest $service): string =>
                    $presenter->dateTime(
                        $service->created_at
                    )
            )
            ->toJson();
    }

    private function managementSupport(
        Request $request,
        Collection $buildingIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            SupportTicket::query()
                ->with([
                    'user:id,first_name,last_name,mobile',
                    'building:id,title',
                ])
                ->whereIn(
                    'building_id',
                    $buildingIds->all()
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'user_name',
                fn (SupportTicket $ticket): string =>
                    $presenter->person(
                        $ticket->user
                    )
            )
            ->addColumn(
                'status_label',
                fn (SupportTicket $ticket): string =>
                    $presenter->statusLabel(
                        $ticket->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (SupportTicket $ticket): string =>
                    $presenter->statusTone(
                        $ticket->status
                    )
            )
            ->addColumn(
                'created_at_jalali',
                fn (SupportTicket $ticket): string =>
                    $presenter->dateTime(
                        $ticket->created_at
                    )
            )
            ->toJson();
    }

    private function residentInvoices(
        Request $request,
        Collection $unitIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            UnitInvoice::query()
                ->with([
                    'unit:id,unit_number,title',
                    'building:id,title,currency',
                ])
                ->whereIn(
                    'unit_id',
                    $unitIds->all()
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyDateColumnFilters(
            $query,
            $request,
            'issue_date'
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'unit_title',
                fn (UnitInvoice $invoice): string =>
                    $presenter->unit(
                        $invoice->unit
                    )
            )
            ->addColumn(
                'total_amount_formatted',
                fn (UnitInvoice $invoice): string =>
                    $presenter->money(
                        $invoice->total_amount
                    )
                    . ' '
                    . (
                        $invoice
                            ->building
                            ?->currency
                        ?: 'IRR'
                    )
            )
            ->addColumn(
                'outstanding_amount_formatted',
                fn (UnitInvoice $invoice): string =>
                    $presenter->money(
                        $invoice->outstanding_amount
                    )
                    . ' '
                    . (
                        $invoice
                            ->building
                            ?->currency
                        ?: 'IRR'
                    )
            )
            ->addColumn(
                'due_date_jalali',
                fn (UnitInvoice $invoice): string =>
                    $presenter->date(
                        $invoice->due_date
                    )
            )
            ->addColumn(
                'status_label',
                fn (UnitInvoice $invoice): string =>
                    $presenter->statusLabel(
                        $invoice->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (UnitInvoice $invoice): string =>
                    $presenter->statusTone(
                        $invoice->status
                    )
            )
            ->addColumn(
                'action_url',
                fn (UnitInvoice $invoice): string =>
                    route(
                        'portal.resident.operations.show',
                        [
                            'resource' =>
                                'invoices',
                            'id' =>
                                $invoice->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function residentReservations(
        Request $request,
        int $userId,
        Collection $unitIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            FacilityReservation::query()
                ->with([
                    'buildingFacility:id,building_id,title',
                    'unit:id,unit_number,title',
                ])
                ->where(
                    'user_id',
                    $userId
                )
                ->whereIn(
                    'unit_id',
                    $unitIds->all()
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyDateColumnFilters(
            $query,
            $request,
            'reservation_date'
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'facility_title',
                fn (FacilityReservation $reservation): string =>
                    $reservation
                        ->buildingFacility
                        ?->title
                    ?: '—'
            )
            ->addColumn(
                'unit_title',
                fn (FacilityReservation $reservation): string =>
                    $presenter->unit(
                        $reservation->unit
                    )
            )
            ->addColumn(
                'reservation_date_jalali',
                fn (FacilityReservation $reservation): string =>
                    $presenter->date(
                        $reservation
                            ->reservation_date
                    )
            )
            ->addColumn(
                'time_range',
                fn (FacilityReservation $reservation): string =>
                    substr(
                        (string) $reservation
                            ->start_time,
                        0,
                        5
                    )
                    . ' تا '
                    . substr(
                        (string) $reservation
                            ->end_time,
                        0,
                        5
                    )
            )
            ->addColumn(
                'final_amount_formatted',
                fn (FacilityReservation $reservation): string =>
                    $presenter->money(
                        $reservation->final_amount
                    )
                    . ' IRR'
            )
            ->addColumn(
                'status_label',
                fn (FacilityReservation $reservation): string =>
                    $presenter->statusLabel(
                        $reservation->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (FacilityReservation $reservation): string =>
                    $presenter->statusTone(
                        $reservation->status
                    )
            )
            ->addColumn(
                'action_url',
                fn (FacilityReservation $reservation): string =>
                    route(
                        'portal.resident.operations.show',
                        [
                            'resource' =>
                                'reservations',
                            'id' =>
                                $reservation->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function residentGuests(
        Request $request,
        Collection $unitIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            GuestVisit::query()
                ->with([
                    'guest:id,first_name,last_name,mobile,vehicle_plate',
                    'unit:id,unit_number,title',
                ])
                ->whereIn(
                    'unit_id',
                    $unitIds->all()
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyDateColumnFilters(
            $query,
            $request,
            'expected_entry_at'
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'guest_name',
                fn (GuestVisit $visit): string =>
                    trim(
                        (
                            $visit
                                ->guest
                                ?->first_name
                            ?? ''
                        )
                        . ' '
                        . (
                            $visit
                                ->guest
                                ?->last_name
                            ?? ''
                        )
                    )
                    ?: 'مهمان'
            )
            ->addColumn(
                'mobile',
                fn (GuestVisit $visit): string =>
                    $visit
                        ->guest
                        ?->mobile
                    ?: '—'
            )
            ->addColumn(
                'unit_title',
                fn (GuestVisit $visit): string =>
                    $presenter->unit(
                        $visit->unit
                    )
            )
            ->addColumn(
                'expected_entry_jalali',
                fn (GuestVisit $visit): string =>
                    $presenter->dateTime(
                        $visit->expected_entry_at
                    )
            )
            ->addColumn(
                'status_label',
                fn (GuestVisit $visit): string =>
                    $presenter->statusLabel(
                        $visit->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (GuestVisit $visit): string =>
                    $presenter->statusTone(
                        $visit->status
                    )
            )
            ->addColumn(
                'action_url',
                fn (GuestVisit $visit): string =>
                    route(
                        'portal.resident.operations.show',
                        [
                            'resource' =>
                                'guests',
                            'id' =>
                                $visit->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function residentServices(
        Request $request,
        int $userId,
        Collection $unitIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            ServiceRequest::query()
                ->with([
                    'assignedTo:id,first_name,last_name,mobile',
                ])
                ->where(
                    'requested_by',
                    $userId
                )
                ->whereIn(
                    'unit_id',
                    $unitIds->all()
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'provider_name',
                fn (ServiceRequest $service): string =>
                    $presenter->person(
                        $service->assignedTo
                    )
            )
            ->addColumn(
                'priority_label',
                fn (ServiceRequest $service): string =>
                    $presenter->priorityLabel(
                        $service->priority
                    )
            )
            ->addColumn(
                'status_label',
                fn (ServiceRequest $service): string =>
                    $presenter->statusLabel(
                        $service->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (ServiceRequest $service): string =>
                    $presenter->statusTone(
                        $service->status
                    )
            )
            ->addColumn(
                'created_at_jalali',
                fn (ServiceRequest $service): string =>
                    $presenter->dateTime(
                        $service->created_at
                    )
            )
            ->addColumn(
                'action_url',
                fn (ServiceRequest $service): string =>
                    route(
                        'portal.resident.operations.show',
                        [
                            'resource' =>
                                'services',
                            'id' =>
                                $service->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function residentSupport(
        Request $request,
        int $userId,
        Collection $unitIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            SupportTicket::query()
                ->with([
                    'supportCategory:id,title',
                    'assignedTo:id,first_name,last_name,mobile',
                ])
                ->where(
                    'user_id',
                    $userId
                )
                ->whereIn(
                    'unit_id',
                    $unitIds->all()
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'category_title',
                fn (SupportTicket $ticket): string =>
                    $ticket
                        ->supportCategory
                        ?->title
                    ?: 'عمومی'
            )
            ->addColumn(
                'assigned_name',
                fn (SupportTicket $ticket): string =>
                    $presenter->person(
                        $ticket->assignedTo
                    )
            )
            ->addColumn(
                'status_label',
                fn (SupportTicket $ticket): string =>
                    $presenter->statusLabel(
                        $ticket->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (SupportTicket $ticket): string =>
                    $presenter->statusTone(
                        $ticket->status
                    )
            )
            ->addColumn(
                'created_at_jalali',
                fn (SupportTicket $ticket): string =>
                    $presenter->dateTime(
                        $ticket->created_at
                    )
            )
            ->addColumn(
                'action_url',
                fn (SupportTicket $ticket): string =>
                    route(
                        'portal.resident.operations.show',
                        [
                            'resource' =>
                                'support',
                            'id' =>
                                $ticket->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function residentWallet(
        Request $request,
        $user,
        Collection $unitIds,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $walletIds =
            $user
                ->wallets()
                ->pluck('id')
                ->merge(
                    \App\Models\Wallet::query()
                        ->whereIn(
                            'owner_type',
                            [
                                (new \App\Models\Unit())
                                    ->getMorphClass(),
                                \App\Models\Unit::class,
                            ]
                        )
                        ->whereIn(
                            'owner_id',
                            $unitIds->all()
                        )
                        ->pluck('id')
                )
                ->unique()
                ->values();

        $query =
            WalletEntry::query()
                ->with([
                    'wallet.owner',
                    'transfer',
                ])
                ->whereIn(
                    'wallet_id',
                    $walletIds->all()
                );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'wallet_label',
                fn (WalletEntry $entry): string =>
                    $presenter->walletLabel(
                        $entry->wallet
                    )
            )
            ->addColumn(
                'entry_type_label',
                fn (WalletEntry $entry): string =>
                    $presenter->enumValue(
                        $entry->entry_type
                    ) === 'credit'
                        ? 'واریز'
                        : 'برداشت'
            )
            ->addColumn(
                'amount_formatted',
                fn (WalletEntry $entry): string =>
                    $presenter->money(
                        $entry->amount
                    )
            )
            ->addColumn(
                'balance_after_formatted',
                fn (WalletEntry $entry): string =>
                    $presenter->money(
                        $entry->balance_after
                    )
            )
            ->addColumn(
                'description',
                fn (WalletEntry $entry): string =>
                    $entry
                        ->transfer
                        ?->description
                    ?: 'تراکنش کیف پول'
            )
            ->addColumn(
                'created_at_jalali',
                fn (WalletEntry $entry): string =>
                    $presenter->dateTime(
                        $entry->created_at
                    )
            )
            ->addColumn(
                'action_url',
                fn (WalletEntry $entry): string =>
                    route(
                        'portal.resident.operations.show',
                        [
                            'resource' =>
                                'wallet',
                            'id' =>
                                $entry->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function providerServices(
        Request $request,
        int $userId,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            ServiceRequest::query()
                ->with([
                    'building:id,title',
                    'unit:id,unit_number,title',
                    'walletPayment',
                ])
                ->where(
                    'assigned_to',
                    $userId
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'building_title',
                fn (ServiceRequest $service): string =>
                    $service
                        ->building
                        ?->title
                    ?: '—'
            )
            ->addColumn(
                'unit_title',
                fn (ServiceRequest $service): string =>
                    $presenter->unit(
                        $service->unit
                    )
            )
            ->addColumn(
                'payment_status_label',
                fn (ServiceRequest $service): string =>
                    $service->walletPayment
                        ? $presenter->statusLabel(
                            $service
                                ->walletPayment
                                ->status
                        )
                        : 'بدون وجه قفل‌شده'
            )
            ->addColumn(
                'status_label',
                fn (ServiceRequest $service): string =>
                    $presenter->statusLabel(
                        $service->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (ServiceRequest $service): string =>
                    $presenter->statusTone(
                        $service->status
                    )
            )
            ->addColumn(
                'action_url',
                fn (ServiceRequest $service): string =>
                    route(
                        'portal.provider.operations.show',
                        [
                            'resource' =>
                                'services',
                            'id' =>
                                $service->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function providerPayouts(
        Request $request,
        int $userId,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $query =
            ProviderPayoutRequest::query()
                ->with(
                    'bankAccount'
                )
                ->where(
                    'provider_user_id',
                    $userId
                );

        $this->applyStatusFilter(
            $query,
            $request
        );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'amount_formatted',
                fn (ProviderPayoutRequest $payout): string =>
                    $presenter->money(
                        $payout->amount
                    )
                    . ' IRR'
            )
            ->addColumn(
                'fee_amount_formatted',
                fn (ProviderPayoutRequest $payout): string =>
                    $presenter->money(
                        $payout->fee_amount
                    )
                    . ' IRR'
            )
            ->addColumn(
                'net_amount_formatted',
                fn (ProviderPayoutRequest $payout): string =>
                    $presenter->money(
                        $payout->net_amount
                    )
                    . ' IRR'
            )
            ->addColumn(
                'bank_label',
                fn (ProviderPayoutRequest $payout): string =>
                    trim(
                        (
                            $payout
                                ->bankAccount
                                ?->bank_name
                            ?? ''
                        )
                        . ' '
                        . (
                            $payout
                                ->bankAccount
                                ?->iban
                            ?? ''
                        )
                    )
                    ?: '—'
            )
            ->addColumn(
                'status_label',
                fn (ProviderPayoutRequest $payout): string =>
                    $presenter->statusLabel(
                        $payout->status
                    )
            )
            ->addColumn(
                'status_tone',
                fn (ProviderPayoutRequest $payout): string =>
                    $presenter->statusTone(
                        $payout->status
                    )
            )
            ->addColumn(
                'created_at_jalali',
                fn (ProviderPayoutRequest $payout): string =>
                    $presenter->dateTime(
                        $payout->created_at
                    )
            )
            ->addColumn(
                'action_url',
                fn (ProviderPayoutRequest $payout): string =>
                    route(
                        'portal.provider.operations.show',
                        [
                            'resource' =>
                                'payouts',
                            'id' =>
                                $payout->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function providerWallet(
        Request $request,
        $user,
        WebDataTablePresenter $presenter
    ): JsonResponse {
        $walletIds =
            $user
                ->wallets()
                ->pluck('id');

        $query =
            WalletEntry::query()
                ->with([
                    'wallet.owner',
                    'transfer',
                ])
                ->whereIn(
                    'wallet_id',
                    $walletIds->all()
                );

        $this->applyCreatedAtFilters(
            $query,
            $request
        );

        $query->latest('id');

        return DataTables::eloquent(
            $query
        )
            ->addColumn(
                'entry_type_label',
                fn (WalletEntry $entry): string =>
                    $presenter->enumValue(
                        $entry->entry_type
                    ) === 'credit'
                        ? 'واریز'
                        : 'برداشت'
            )
            ->addColumn(
                'amount_formatted',
                fn (WalletEntry $entry): string =>
                    $presenter->money(
                        $entry->amount
                    )
            )
            ->addColumn(
                'balance_after_formatted',
                fn (WalletEntry $entry): string =>
                    $presenter->money(
                        $entry->balance_after
                    )
            )
            ->addColumn(
                'description',
                fn (WalletEntry $entry): string =>
                    $entry
                        ->transfer
                        ?->description
                    ?: 'تراکنش کیف پول'
            )
            ->addColumn(
                'created_at_jalali',
                fn (WalletEntry $entry): string =>
                    $presenter->dateTime(
                        $entry->created_at
                    )
            )
            ->addColumn(
                'action_url',
                fn (WalletEntry $entry): string =>
                    route(
                        'portal.provider.operations.show',
                        [
                            'resource' =>
                                'wallet',
                            'id' =>
                                $entry->getKey(),
                        ]
                    )
            )
            ->toJson();
    }

    private function applyStatusFilter(
        Builder $query,
        Request $request
    ): void {
        if (
            $request->filled(
                'status'
            )
        ) {
            $query->where(
                'status',
                $request->string(
                    'status'
                )->toString()
            );
        }
    }

    private function applyCreatedAtFilters(
        Builder $query,
        Request $request
    ): void {
        $this->applyDateColumnFilters(
            $query,
            $request,
            'created_at'
        );
    }

    private function applyDateColumnFilters(
        Builder $query,
        Request $request,
        string $column
    ): void {
        if (
            $request->filled(
                'from'
            )
        ) {
            $query->whereDate(
                $column,
                '>=',
                $request->string(
                    'from'
                )->toString()
            );
        }

        if (
            $request->filled(
                'to'
            )
        ) {
            $query->whereDate(
                $column,
                '<=',
                $request->string(
                    'to'
                )->toString()
            );
        }
    }
}
