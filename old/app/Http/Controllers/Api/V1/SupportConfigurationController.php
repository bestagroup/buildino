<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportCategoryRequest;
use App\Http\Requests\StoreSupportSlaPolicyRequest;
use App\Http\Requests\UpdateSupportCategoryRequest;
use App\Http\Requests\UpdateSupportSlaPolicyRequest;
use App\Models\SupportCategory;
use App\Models\SupportSlaPolicy;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportConfigurationController extends Controller
{
    public function categories(
        Request $request,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->authorizeView($request, $permissions);

        return response()->json([
            'data' => SupportCategory::query()
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function storeCategory(
        StoreSupportCategoryRequest $request,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->authorizeManage($request, $permissions);

        return response()->json([
            'data' => SupportCategory::query()->create(
                $request->validated()
            ),
        ], 201);
    }

    public function updateCategory(
        UpdateSupportCategoryRequest $request,
        SupportCategory $supportCategory,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->authorizeManage($request, $permissions);

        $supportCategory->update(
            $request->validated()
        );

        return response()->json([
            'data' => $supportCategory->refresh(),
        ]);
    }

    public function slaPolicies(
        Request $request,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->authorizeView($request, $permissions);

        return response()->json([
            'data' => SupportSlaPolicy::query()
                ->with('supportCategory:id,title')
                ->orderBy('support_category_id')
                ->orderBy('priority')
                ->get(),
        ]);
    }

    public function storeSlaPolicy(
        StoreSupportSlaPolicyRequest $request,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->authorizeManage($request, $permissions);

        $data = $request->validated();

        $existing = SupportSlaPolicy::query()
            ->where('priority', $data['priority'])
            ->when(
                $data['support_category_id'] ?? null,
                fn ($query, $categoryId) => $query->where(
                    'support_category_id',
                    $categoryId
                ),
                fn ($query) => $query->whereNull(
                    'support_category_id'
                )
            )
            ->first();

        if ($existing) {
            $existing->update($data);
            $model = $existing->refresh();
            $status = 200;
        } else {
            $model = SupportSlaPolicy::query()->create($data);
            $status = 201;
        }

        return response()->json([
            'data' => $model,
        ], $status);
    }

    public function updateSlaPolicy(
        UpdateSupportSlaPolicyRequest $request,
        SupportSlaPolicy $supportSlaPolicy,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->authorizeManage($request, $permissions);

        $supportSlaPolicy->update(
            $request->validated()
        );

        return response()->json([
            'data' => $supportSlaPolicy->refresh(),
        ]);
    }

    private function authorizeView(
        Request $request,
        PermissionChecker $permissions
    ): void {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'support-config.view',
                null
            ) || $permissions->allows(
                $request->user(),
                'support-config.manage',
                null
            ),
            403
        );
    }

    private function authorizeManage(
        Request $request,
        PermissionChecker $permissions
    ): void {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'support-config.manage',
                null
            ),
            403
        );
    }
}
