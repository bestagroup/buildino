<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BuildingExpense\CreateBuildingExpense;
use App\Actions\BuildingExpense\UpdateBuildingExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingExpenseRequest;
use App\Http\Requests\UpdateBuildingExpenseRequest;
use App\Models\BuildingExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BuildingExpense::class);

        $items = BuildingExpense::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreBuildingExpenseRequest $request, CreateBuildingExpense $action): JsonResponse
    {
        $this->authorize('create', BuildingExpense::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(BuildingExpense $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateBuildingExpenseRequest $request, BuildingExpense $model, UpdateBuildingExpense $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(BuildingExpense $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
