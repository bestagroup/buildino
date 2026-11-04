<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FinancialCategory;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request, PermissionChecker $permissions): JsonResponse
    {
        abort_unless($permissions->allowsAnyScope($request->user(), 'charge-formulas.view'), 403);
        return response()->json(FinancialCategory::query()
            ->where('type', 'expense')
            ->latest('id')->paginate(min(max($request->integer('per_page', 20), 1), 100)));
    }

    public function store(Request $request, PermissionChecker $permissions): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);
        abort_unless($permissions->allowsAnyScope($request->user(), 'charge-formulas.create'), 403);

        $category = DB::transaction(function () use ($data) {
            $title = trim($data['title']);
            if ($title === '' || FinancialCategory::query()
                ->where('type', 'expense')->where('title', $title)->exists()) {
                throw ValidationException::withMessages(['title' => 'عنوان دسته هزینه خالی یا تکراری است.']);
            }

            return FinancialCategory::query()->create([
                'building_id' => null, 'title' => $title,
                'type' => 'expense', 'is_active' => true,
            ]);
        }, 3);

        return response()->json(['data' => $category], 201);
    }
}
