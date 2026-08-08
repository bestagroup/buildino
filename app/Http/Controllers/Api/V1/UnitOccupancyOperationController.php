<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UnitOccupancy\AssignUnitOccupancy;
use App\Actions\UnitOccupancy\EndUnitOccupancy;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitOccupancyRequest;
use App\Models\UnitOccupancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitOccupancyOperationController extends Controller
{
    public function store(StoreUnitOccupancyRequest $request, AssignUnitOccupancy $action): JsonResponse
    {
        $this->authorize('create', UnitOccupancy::class);
        return response()->json(['data' => $action->execute($request->validated(), $request->user())], 201);
    }

    public function end(Request $request, UnitOccupancy $unitOccupancy, EndUnitOccupancy $action): JsonResponse
    {
        $this->authorize('update', $unitOccupancy);
        $data = $request->validate(['ends_at' => ['nullable', 'date']]);
        return response()->json(['data' => $action->execute($unitOccupancy, $request->user(), $data['ends_at'] ?? null)]);
    }
}
