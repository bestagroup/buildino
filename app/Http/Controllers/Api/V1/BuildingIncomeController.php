<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BuildingIncome\CreateBuildingIncome;
use App\Actions\BuildingIncome\UpdateBuildingIncome;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingIncomeRequest;
use App\Http\Requests\UpdateBuildingIncomeRequest;
use App\Models\Building;
use App\Models\BuildingIncome;
use App\Models\User;
use App\Services\Security\BuildingResourceScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingIncomeController extends Controller
{
    public function index(
        Request $request,
        BuildingResourceScopeService $scope
    ): JsonResponse {
        $this->authorize('viewAny', BuildingIncome::class);

        /** @var User $user */
        $user = $request->user();

        $items = $scope->apply(
            BuildingIncome::query(),
            $user,
            'incomes.view'
        )
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreBuildingIncomeRequest $request, CreateBuildingIncome $action): JsonResponse
    {
        $data = $request->validated();
        $building = Building::query()->findOrFail(
            $data['building_id']
        );

        $this->authorize(
            'create',
            [BuildingIncome::class, $building]
        );

        /** @var User $user */
        $user = $request->user();
        $income = $action->execute($data, $user);

        return response()->json(['data' => $income], 201);
    }

    public function show(BuildingIncome $income): JsonResponse
    {
        $this->authorize('view', $income);
        return response()->json(['data' => $income]);
    }

    public function update(UpdateBuildingIncomeRequest $request, BuildingIncome $income, UpdateBuildingIncome $action): JsonResponse
    {
        $this->authorize('update', $income);
        return response()->json(['data' => $action->execute($income, $request->validated())]);
    }

    public function destroy(BuildingIncome $income): JsonResponse
    {
        $this->authorize('delete', $income);
        $income->delete();
        return response()->json(status: 204);
    }
}
