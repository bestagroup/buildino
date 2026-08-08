<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BuildingIncome\CreateBuildingIncome;
use App\Actions\BuildingIncome\UpdateBuildingIncome;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingIncomeRequest;
use App\Http\Requests\UpdateBuildingIncomeRequest;
use App\Models\BuildingIncome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingIncomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BuildingIncome::class);

        $items = BuildingIncome::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreBuildingIncomeRequest $request, CreateBuildingIncome $action): JsonResponse
    {
        $this->authorize('create', BuildingIncome::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(BuildingIncome $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateBuildingIncomeRequest $request, BuildingIncome $model, UpdateBuildingIncome $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(BuildingIncome $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
