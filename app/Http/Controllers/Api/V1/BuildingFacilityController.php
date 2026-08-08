<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BuildingFacility\CreateBuildingFacility;
use App\Actions\BuildingFacility\UpdateBuildingFacility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingFacilityRequest;
use App\Http\Requests\UpdateBuildingFacilityRequest;
use App\Models\BuildingFacility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingFacilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BuildingFacility::class);

        $items = BuildingFacility::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreBuildingFacilityRequest $request, CreateBuildingFacility $action): JsonResponse
    {
        $this->authorize('create', BuildingFacility::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(BuildingFacility $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateBuildingFacilityRequest $request, BuildingFacility $model, UpdateBuildingFacility $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(BuildingFacility $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
