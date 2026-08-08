<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FacilityReservation\ApproveFacilityReservation;
use App\Actions\FacilityReservation\CreateFacilityReservation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFacilityReservationRequest;
use App\Models\FacilityReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacilityReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FacilityReservation::class);
        $query = FacilityReservation::query()->with(['buildingFacility', 'unit', 'user'])->latest('id');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('unit_id')) $query->where('unit_id', $request->integer('unit_id'));
        return response()->json($query->paginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function store(StoreFacilityReservationRequest $request, CreateFacilityReservation $action): JsonResponse
    {
        $this->authorize('create', FacilityReservation::class);
        return response()->json(['data' => $action->execute($request->validated())], 201);
    }

    public function show(FacilityReservation $facilityReservation): JsonResponse
    {
        $this->authorize('view', $facilityReservation);
        return response()->json(['data' => $facilityReservation->load(['buildingFacility', 'unit', 'user'])]);
    }

    public function approve(FacilityReservation $facilityReservation, ApproveFacilityReservation $action): JsonResponse
    {
        $this->authorize('approve', $facilityReservation);
        return response()->json(['data' => $action->execute($facilityReservation, request()->user())]);
    }
}
