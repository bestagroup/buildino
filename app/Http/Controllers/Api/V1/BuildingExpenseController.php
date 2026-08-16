<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BuildingExpense\CreateBuildingExpense;
use App\Actions\BuildingExpense\UpdateBuildingExpense;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingExpenseRequest;
use App\Http\Requests\UpdateBuildingExpenseRequest;
use App\Models\Building;
use App\Models\BuildingExpense;
use App\Models\User;
use App\Services\Security\BuildingResourceScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingExpenseController extends Controller
{
    public function index(
        Request $request,
        BuildingResourceScopeService $scope
    ): JsonResponse {
        $this->authorize('viewAny', BuildingExpense::class);

        /** @var User $user */
        $user = $request->user();

        $items = $scope->apply(
            BuildingExpense::query(),
            $user,
            'expenses.view'
        )
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreBuildingExpenseRequest $request, CreateBuildingExpense $action): JsonResponse
    {
        $data = $request->validated();
        $building = Building::query()->findOrFail(
            $data['building_id']
        );

        $this->authorize(
            'create',
            [BuildingExpense::class, $building]
        );

        /** @var User $user */
        $user = $request->user();
        $expense = $action->execute($data, $user);

        return response()->json(['data' => $expense], 201);
    }

    public function show(BuildingExpense $expense): JsonResponse
    {
        $this->authorize('view', $expense);
        return response()->json(['data' => $expense]);
    }

    public function update(UpdateBuildingExpenseRequest $request, BuildingExpense $expense, UpdateBuildingExpense $action): JsonResponse
    {
        $this->authorize('update', $expense);
        return response()->json(['data' => $action->execute($expense, $request->validated())]);
    }

    public function destroy(BuildingExpense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);
        $expense->delete();
        return response()->json(status: 204);
    }
}
