<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Building\CreateBuilding;
use App\Actions\Building\UpdateBuilding;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingRequest;
use App\Http\Requests\UpdateBuildingRequest;
use App\Models\Building;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Building::class);

        $items = Building::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreBuildingRequest $request, CreateBuilding $action): JsonResponse
    {
        $this->authorize('create', Building::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(Building $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateBuildingRequest $request, Building $model, UpdateBuilding $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(Building $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
