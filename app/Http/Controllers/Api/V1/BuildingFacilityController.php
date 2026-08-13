<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingFacilityRequest;
use App\Http\Requests\UpdateBuildingFacilityRequest;
use App\Http\Resources\V1\BuildingFacilityResource;
use App\Models\Building;
use App\Models\BuildingFacility;
use App\Services\Security\FacilityAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BuildingFacilityController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        FacilityAccessService $access
    ): AnonymousResourceCollection {
        abort_unless(
            $access->canAccessBuilding(
                $request->user(),
                $building
            ),
            403
        );

        $query = $building->buildingFacilities()
            ->withCount('facilityReservations');

        if (! $access->canViewAllFacilities(
            $request->user(),
            $building
        )) {
            $query->where('is_active', true);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if (
            $request->has('is_active')
            && $access->canViewAllFacilities(
                $request->user(),
                $building
            )
        ) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        return BuildingFacilityResource::collection(
            $query
                ->orderBy('title')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    public function store(
        StoreBuildingFacilityRequest $request,
        Building $building,
        FacilityAccessService $access
    ) {
        abort_unless(
            $access->canCreateFacility(
                $request->user(),
                $building
            ),
            403
        );

        $facility = $building
            ->buildingFacilities()
            ->create(
                $request->validated()
            );

        $facility->load('building:id,complex_id,code,title');
        $facility->loadCount('facilityReservations');

        return (new BuildingFacilityResource($facility))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        BuildingFacility $buildingFacility,
        FacilityAccessService $access
    ): BuildingFacilityResource {
        abort_unless(
            $access->canViewFacility(
                $request->user(),
                $buildingFacility
            ),
            403
        );

        if (
            ! $buildingFacility->is_active
            && ! $access->canManageFacility(
                $request->user(),
                $buildingFacility,
                'view'
            )
        ) {
            abort(404);
        }

        $buildingFacility->load([
            'building:id,complex_id,code,title',
            'facilitySchedules.facilityTimeSlots',
            'facilityReservationRules',
        ]);

        $buildingFacility->loadCount('facilityReservations');

        return new BuildingFacilityResource(
            $buildingFacility
        );
    }

    public function update(
        UpdateBuildingFacilityRequest $request,
        BuildingFacility $buildingFacility,
        FacilityAccessService $access
    ): BuildingFacilityResource {
        abort_unless(
            $access->canManageFacility(
                $request->user(),
                $buildingFacility,
                'update'
            ),
            403
        );

        $buildingFacility->update(
            $request->validated()
        );

        $buildingFacility = $buildingFacility->refresh();

        $buildingFacility->load([
            'building:id,complex_id,code,title',
            'facilitySchedules.facilityTimeSlots',
            'facilityReservationRules',
        ]);

        $buildingFacility->loadCount('facilityReservations');

        return new BuildingFacilityResource(
            $buildingFacility
        );
    }

    public function destroy(
        Request $request,
        BuildingFacility $buildingFacility,
        FacilityAccessService $access
    ): Response {
        abort_unless(
            $access->canManageFacility(
                $request->user(),
                $buildingFacility,
                'delete'
            ),
            403
        );

        $hasActiveReservations = $buildingFacility
            ->facilityReservations()
            ->whereIn(
                'status',
                [
                    ReservationStatus::Pending->value,
                    ReservationStatus::PaymentPending->value,
                    ReservationStatus::Approved->value,
                    ReservationStatus::Confirmed->value,
                ]
            )
            ->exists();

        abort_if(
            $hasActiveReservations,
            409,
            'Facility with active reservations cannot be deleted.'
        );

        $buildingFacility->delete();

        return response()->noContent();
    }
}
