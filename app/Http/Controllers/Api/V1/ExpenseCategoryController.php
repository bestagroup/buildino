<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\FinancialCategory;
use App\Services\Finance\ExpenseCategoryService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExpenseCategoryController extends Controller
{
    public function index(Request $request, PermissionChecker $permissions): JsonResponse
    {
        $request->validate(['building_id' => 'required|integer|exists:buildings,id']);
        $building = Building::findOrFail($request->integer('building_id'));
        abort_unless($permissions->allows($request->user(), 'charge-formulas.view', $building), 403);
        $items = FinancialCategory::where('building_id', $building->id)->where('type', 'expense')
            ->with('parent:id,title,is_active')->orderByRaw('COALESCE(parent_id, id)')->orderBy('parent_id')->orderBy('title')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));
        return response()->json($items->toArray() + ['parents' => FinancialCategory::where('building_id', $building->id)
            ->where('type', 'expense')->whereNull('parent_id')->orderBy('title')->get(['id', 'title', 'is_active'])]);
    }

    public function store(Request $request, PermissionChecker $permissions, ExpenseCategoryService $service): JsonResponse
    {
        $data = $this->validated($request);
        $building = Building::findOrFail($data['building_id']);
        abort_unless($permissions->allows($request->user(), 'charge-formulas.create', $building), 403);
        return response()->json(['data' => $service->save($building, $data)], 201);
    }

    public function update(Request $request, FinancialCategory $expenseCategory, PermissionChecker $permissions, ExpenseCategoryService $service): JsonResponse
    {
        abort_unless($expenseCategory->building_id && $expenseCategory->type->value === 'expense', 404);
        $building = Building::findOrFail($expenseCategory->building_id);
        abort_unless($permissions->allows($request->user(), 'charge-formulas.update', $building), 403);
        $data = $this->validated($request);
        abort_unless((int) $data['building_id'] === $building->id, 403);
        return response()->json(['data' => $service->save($building, $data, $expenseCategory)]);
    }

    private function validated(Request $request): array
    {
        return $request->validate(['building_id' => 'required|integer|exists:buildings,id',
            'parent_id' => 'nullable|integer|min:1', 'title' => 'required|string|max:255', 'is_active' => 'sometimes|boolean']);
    }
}
