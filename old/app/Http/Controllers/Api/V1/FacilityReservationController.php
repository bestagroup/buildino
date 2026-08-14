<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelFacilityReservationRequest;
use App\Http\Requests\StoreFacilityReservationRequest;
use App\Http\Requests\PayFacilityReservationRequest;
use App\Http\Resources\V1\FacilityReservationResource;
use App\Models\Building;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\Unit;
use App\Services\FacilityReservationService;
use App\Services\Facility\FacilityWalletPaymentService;
use App\Enums\FacilityWalletPayerSource;
use App\Services\Security\FacilityAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FacilityReservationController extends Controller
{
    public function index(
        Request $request,
        FacilityAccessService $access
    ): AnonymousResourceCollection {
        $query = FacilityReservation::query()
            ->with([
                'buildingFacility:id,building_id,title,code,type',
                'facilityTimeSlot:id,facility_schedule_id,start_time,end_time,capacity,price',
                'unit:id,floor_id,unit_number,title',
                'user:id,first_name,last_name',
            ]);

        if ($buildingId = $request->integer('building_id')) {
            $building = Building::query()
                ->findOrFail($buildingId);

            abort_unless(
                $access->canAccessBuilding(
                    $request->user(),
                    $building
                ),
                403
            );

            $query->whereHas(
                'buildingFacility',
                fn (Builder $query) => $query->where(
                    'building_id',
                    $building->getKey()
                )
            );

            if (! $access->canViewAllReservationsInBuilding(
                $request->user(),
                $building
            )) {
                $query->where(
                    'user_id',
                    $request->user()->getKey()
                );
            }
        } elseif (! $access->canViewAllReservationsGlobally(
            $request->user()
        )) {
            $query->where(
                'user_id',
                $request->user()->getKey()
            );
        }

        if ($facilityId = $request->integer('building_facility_id')) {
            $query->where(
                'building_facility_id',
                $facilityId
            );
        }

        if ($unitId = $request->integer('unit_id')) {
            $query->where(
                'unit_id',
                $unitId
            );
        }

        if ($status = $request->query('status')) {
            $query->where(
                'status',
                $status
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'reservation_date',
                '>=',
                $request->query('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'reservation_date',
                '<=',
                $request->query('date_to')
            );
        }

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        return FacilityReservationResource::collection(
            $query
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    public function store(
        StoreFacilityReservationRequest $request,
        BuildingFacility $buildingFacility,
        FacilityReservationService $service,
        FacilityAccessService $access
    ) {
        $unit = Unit::query()
            ->with('floor.block.building')
            ->findOrFail(
                $request->validated('unit_id')
            );

        abort_unless(
            $access->canReserveForUnit(
                $request->user(),
                $buildingFacility,
                $unit
            ),
            403
        );

        $reservation = $service->create([
            ...$request->validated(),
            'building_facility_id' => $buildingFacility->getKey(),
            'user_id' => $request->user()->getKey(),
        ]);

        $this->loadReservation(
            $reservation
        );

        return (new FacilityReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        FacilityReservation $facilityReservation,
        FacilityAccessService $access
    ): FacilityReservationResource {
        abort_unless(
            $access->canViewReservation(
                $request->user(),
                $facilityReservation
            ),
            403
        );

        $this->loadReservation(
            $facilityReservation
        );

        return new FacilityReservationResource(
            $facilityReservation
        );
    }


    public function pay(
        PayFacilityReservationRequest $request,
        FacilityReservation $facilityReservation,
        FacilityWalletPaymentService $service,
        FacilityAccessService $access
    ): FacilityReservationResource {
        abort_unless(
            $access->canViewReservation(
                $request->user(),
                $facilityReservation
            ),
            403
        );

        $service->pay(
            $facilityReservation,
            $request->user(),
            FacilityWalletPayerSource::from(
                $request->validated('payer_source')
            )
        );

        $facilityReservation->refresh();

        $this->loadReservation(
            $facilityReservation
        );

        return new FacilityReservationResource(
            $facilityReservation
        );
    }

    public function approve(
        Request $request,
        FacilityReservation $facilityReservation,
        FacilityReservationService $service,
        FacilityAccessService $access
    ): FacilityReservationResource {
        abort_unless(
            $access->canApproveReservation(
                $request->user(),
                $facilityReservation
            ),
            403
        );

        $reservation = $service->approve(
            $facilityReservation,
            $request->user()
        );

        $this->loadReservation(
            $reservation
        );

        return new FacilityReservationResource(
            $reservation
        );
    }

    public function reject(
        Request $request,
        FacilityReservation $facilityReservation,
        FacilityReservationService $service,
        FacilityAccessService $access
    ): FacilityReservationResource {
        abort_unless(
            $access->canApproveReservation(
                $request->user(),
                $facilityReservation
            ),
            403
        );

        $reservation = $service->reject(
            $facilityReservation,
            $request->user()
        );

        $this->loadReservation(
            $reservation
        );

        return new FacilityReservationResource(
            $reservation
        );
    }

    public function cancel(
        CancelFacilityReservationRequest $request,
        FacilityReservation $facilityReservation,
        FacilityReservationService $service,
        FacilityAccessService $access
    ): FacilityReservationResource {
        abort_unless(
            $access->canCancelReservation(
                $request->user(),
                $facilityReservation
            ),
            403
        );

        $isOwner = (int) $facilityReservation->user_id
            === (int) $request->user()->getKey();

        $force = ! $isOwner
            && $access->canForceCancelReservation(
                $request->user(),
                $facilityReservation
            );

        $reservation = $service->cancel(
            $facilityReservation,
            $request->user(),
            $request->validated('reason'),
            $force
        );

        $this->loadReservation(
            $reservation
        );

        return new FacilityReservationResource(
            $reservation
        );
    }

    private function loadReservation(
        FacilityReservation $reservation
    ): void {
        $reservation->load([
            'buildingFacility:id,building_id,title,code,type',
            'facilityTimeSlot:id,facility_schedule_id,start_time,end_time,capacity,price',
            'unit:id,floor_id,unit_number,title',
            'user:id,first_name,last_name',
            'approvedBy:id,first_name,last_name',
            'reservationCancellations',
            'walletPayment.sourceWallet',
            'walletPayment.buildingWallet',
        ]);
    }
}
