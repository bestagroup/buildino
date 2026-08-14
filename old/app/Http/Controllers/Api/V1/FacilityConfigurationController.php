<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacilityAvailabilityRequest;
use App\Http\Requests\StoreFacilityBlackoutRequest;
use App\Http\Requests\StoreFacilityScheduleRequest;
use App\Http\Requests\StoreFacilityTimeSlotRequest;
use App\Http\Requests\UpdateFacilityScheduleRequest;
use App\Http\Requests\UpdateFacilityTimeSlotRequest;
use App\Http\Requests\UpsertFacilityReservationRuleRequest;
use App\Http\Resources\V1\FacilityBlackoutResource;
use App\Http\Resources\V1\FacilityReservationRuleResource;
use App\Http\Resources\V1\FacilityScheduleResource;
use App\Http\Resources\V1\FacilityTimeSlotResource;
use App\Models\BuildingFacility;
use App\Models\FacilityBlackout;
use App\Models\FacilitySchedule;
use App\Models\FacilityTimeSlot;
use App\Services\FacilityAvailabilityService;
use App\Services\FacilityConfigurationService;
use App\Services\Security\FacilityAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FacilityConfigurationController extends Controller
{
    public function schedules(
        Request $request,
        BuildingFacility $buildingFacility,
        FacilityAccessService $access
    ): AnonymousResourceCollection {
        $this->authorizeView(
            $request,
            $buildingFacility,
            $access
        );

        $schedules = $buildingFacility
            ->facilitySchedules()
            ->with('facilityTimeSlots')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return FacilityScheduleResource::collection(
            $schedules
        );
    }

    public function storeSchedule(
        StoreFacilityScheduleRequest $request,
        BuildingFacility $buildingFacility,
        FacilityConfigurationService $service,
        FacilityAccessService $access
    ) {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        $schedule = $service->createSchedule(
            $buildingFacility,
            $request->validated()
        );

        $schedule->load('facilityTimeSlots');

        return (new FacilityScheduleResource($schedule))
            ->response()
            ->setStatusCode(201);
    }

    public function updateSchedule(
        UpdateFacilityScheduleRequest $request,
        BuildingFacility $buildingFacility,
        FacilitySchedule $facilitySchedule,
        FacilityConfigurationService $service,
        FacilityAccessService $access
    ): FacilityScheduleResource {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        $this->ensureScheduleBelongsToFacility(
            $facilitySchedule,
            $buildingFacility
        );

        $schedule = $service->updateSchedule(
            $facilitySchedule,
            $request->validated()
        );

        $schedule->load('facilityTimeSlots');

        return new FacilityScheduleResource(
            $schedule
        );
    }

    public function destroySchedule(
        Request $request,
        BuildingFacility $buildingFacility,
        FacilitySchedule $facilitySchedule,
        FacilityAccessService $access
    ): Response {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        $this->ensureScheduleBelongsToFacility(
            $facilitySchedule,
            $buildingFacility
        );

        $facilitySchedule->delete();

        return response()->noContent();
    }

    public function storeTimeSlot(
        StoreFacilityTimeSlotRequest $request,
        BuildingFacility $buildingFacility,
        FacilitySchedule $facilitySchedule,
        FacilityConfigurationService $service,
        FacilityAccessService $access
    ) {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        $this->ensureScheduleBelongsToFacility(
            $facilitySchedule,
            $buildingFacility
        );

        $slot = $service->createTimeSlot(
            $facilitySchedule,
            $request->validated()
        );

        return (new FacilityTimeSlotResource($slot))
            ->response()
            ->setStatusCode(201);
    }

    public function updateTimeSlot(
        UpdateFacilityTimeSlotRequest $request,
        BuildingFacility $buildingFacility,
        FacilitySchedule $facilitySchedule,
        FacilityTimeSlot $facilityTimeSlot,
        FacilityConfigurationService $service,
        FacilityAccessService $access
    ): FacilityTimeSlotResource {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        $this->ensureTimeSlotBelongsToSchedule(
            $facilitySchedule,
            $facilityTimeSlot,
            $buildingFacility
        );

        return new FacilityTimeSlotResource(
            $service->updateTimeSlot(
                $facilityTimeSlot,
                $request->validated()
            )
        );
    }

    public function destroyTimeSlot(
        Request $request,
        BuildingFacility $buildingFacility,
        FacilitySchedule $facilitySchedule,
        FacilityTimeSlot $facilityTimeSlot,
        FacilityAccessService $access
    ): Response {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        $this->ensureTimeSlotBelongsToSchedule(
            $facilitySchedule,
            $facilityTimeSlot,
            $buildingFacility
        );

        $hasReservations = $facilityTimeSlot
            ->facilityReservations()
            ->exists();

        abort_if(
            $hasReservations,
            409,
            'Time slot with reservation history cannot be deleted.'
        );

        $facilityTimeSlot->delete();

        return response()->noContent();
    }

    public function reservationRule(
        Request $request,
        BuildingFacility $buildingFacility,
        FacilityAccessService $access
    ): JsonResponse|FacilityReservationRuleResource {
        $this->authorizeView(
            $request,
            $buildingFacility,
            $access
        );

        $rule = $buildingFacility
            ->facilityReservationRules()
            ->first();

        if (! $rule) {
            return response()->json([
                'data' => null,
            ]);
        }

        return new FacilityReservationRuleResource(
            $rule
        );
    }

    public function upsertReservationRule(
        UpsertFacilityReservationRuleRequest $request,
        BuildingFacility $buildingFacility,
        FacilityConfigurationService $service,
        FacilityAccessService $access
    ) {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        $rule = $service->upsertRule(
            $buildingFacility,
            $request->validated()
        );

        return (new FacilityReservationRuleResource($rule))
            ->response()
            ->setStatusCode(200);
    }

    public function blackouts(
        Request $request,
        BuildingFacility $buildingFacility,
        FacilityAccessService $access
    ): AnonymousResourceCollection {
        $this->authorizeView(
            $request,
            $buildingFacility,
            $access
        );

        return FacilityBlackoutResource::collection(
            $buildingFacility
                ->facilityBlackouts()
                ->with('createdBy:id,first_name,last_name')
                ->orderBy('starts_at')
                ->get()
        );
    }

    public function storeBlackout(
        StoreFacilityBlackoutRequest $request,
        BuildingFacility $buildingFacility,
        FacilityConfigurationService $service,
        FacilityAccessService $access
    ) {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        $blackout = $service->createBlackout(
            $buildingFacility,
            $request->user(),
            $request->validated()
        );

        $blackout->load(
            'createdBy:id,first_name,last_name'
        );

        return (new FacilityBlackoutResource($blackout))
            ->response()
            ->setStatusCode(201);
    }

    public function destroyBlackout(
        Request $request,
        BuildingFacility $buildingFacility,
        FacilityBlackout $facilityBlackout,
        FacilityAccessService $access
    ): Response {
        $this->authorizeManage(
            $request,
            $buildingFacility,
            $access
        );

        abort_unless(
            (int) $facilityBlackout->building_facility_id
                === (int) $buildingFacility->getKey(),
            404
        );

        $facilityBlackout->delete();

        return response()->noContent();
    }

    public function availability(
        FacilityAvailabilityRequest $request,
        BuildingFacility $buildingFacility,
        FacilityAvailabilityService $service,
        FacilityAccessService $access
    ): JsonResponse {
        $this->authorizeView(
            $request,
            $buildingFacility,
            $access
        );

        return response()->json([
            'data' => $service->forDate(
                $buildingFacility,
                $request->validated('date')
            ),
        ]);
    }

    private function authorizeView(
        Request $request,
        BuildingFacility $facility,
        FacilityAccessService $access
    ): void {
        abort_unless(
            $access->canViewFacility(
                $request->user(),
                $facility
            ),
            403
        );
    }

    private function authorizeManage(
        Request $request,
        BuildingFacility $facility,
        FacilityAccessService $access
    ): void {
        abort_unless(
            $access->canManageFacility(
                $request->user(),
                $facility,
                'update'
            ),
            403
        );
    }

    private function ensureScheduleBelongsToFacility(
        FacilitySchedule $schedule,
        BuildingFacility $facility
    ): void {
        abort_unless(
            (int) $schedule->building_facility_id
                === (int) $facility->getKey(),
            404
        );
    }

    private function ensureTimeSlotBelongsToSchedule(
        FacilitySchedule $schedule,
        FacilityTimeSlot $slot,
        BuildingFacility $facility
    ): void {
        $this->ensureScheduleBelongsToFacility(
            $schedule,
            $facility
        );

        abort_unless(
            (int) $slot->facility_schedule_id
                === (int) $schedule->getKey(),
            404
        );
    }
}
