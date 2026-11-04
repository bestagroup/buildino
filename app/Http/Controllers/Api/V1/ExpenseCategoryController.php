<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\FinancialCategory;
use App\Services\Web\ManagementDashboardAccessService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request, ManagementDashboardAccessService $access, PermissionChecker $permissions): JsonResponse
    {
        abort_unless($permissions->allowsAnyScope($request->user(), 'charge-formulas.view'), 403);
        $request->validate(['building_id' => 'nullable|integer|exists:buildings,id']);
        $ids = $access->accessibleBuildings($request->user())
            ->filter(fn (Building $building) => $permissions->allows($request->user(), 'charge-formulas.view', $building))
            ->modelKeys();

        return response()->json(FinancialCategory::query()
            ->where('type', 'expense')
            ->whereIn('building_id', $ids)
            ->when($request->filled('building_id'), fn ($query) => $query->where('building_id', $request->integer('building_id')))
            ->latest('id')->paginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function store(Request $request, PermissionChecker $permissions): JsonResponse
    {
        $data = $request->validate([
            'building_id' => 'required|integer|exists:buildings,id',
            'title' => 'required|string|max:255',
        ]);
        $building = Building::query()->findOrFail($data['building_id']);
        abort_unless($permissions->allows($request->user(), 'charge-formulas.create', $building), 403);

        $category = DB::transaction(function () use ($data, $building) {
            Building::query()->whereKey($building->id)->lockForUpdate()->firstOrFail();
            $title = trim($data['title']);
            if ($title === '' || FinancialCategory::query()->where('building_id', $building->id)
                ->where('type', 'expense')->where('title', $title)->exists()) {
                throw ValidationException::withMessages(['title' => 'عنوان دسته هزینه خالی یا تکراری است.']);
            }

            return FinancialCategory::query()->create([
                'building_id' => $building->id, 'title' => $title,
                'type' => 'expense', 'is_active' => true,
            ]);
        }, 3);

        return response()->json(['data' => $category], 201);
    }
}
