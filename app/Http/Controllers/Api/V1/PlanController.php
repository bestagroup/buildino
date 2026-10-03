<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FeatureValueType;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $permissions
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        return response()->json([
            'data' => Plan::query()
                ->with('features:id,code,title,value_type')
                ->orderBy('price')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        $data = $this->validatePlan($request);

        $plan = DB::transaction(function () use ($data): Plan {
            $plan = Plan::query()->create(
                collect($data)->except('features')->all()
            );

            $this->syncFeatures($plan, $data['features'] ?? []);

            return $plan;
        });

        return response()->json([
            'data' => $plan->load('features'),
        ], 201);
    }

    public function update(
        Request $request,
        Plan $plan
    ): JsonResponse {
        $this->authorizePlatform($request);

        $data = $this->validatePlan($request, $plan);

        DB::transaction(function () use ($plan, $data): void {
            $plan->fill(
                collect($data)->except('features')->all()
            )->save();

            if (array_key_exists('features', $data)) {
                $this->syncFeatures($plan, $data['features'] ?? []);
            }
        });

        return response()->json([
            'data' => $plan->refresh()->load('features'),
        ]);
    }

    public function features(Request $request): JsonResponse
    {
        $this->authorizePlatform($request);

        return response()->json([
            'data' => Feature::query()
                ->orderBy('code')
                ->get(),
        ]);
    }

    private function validatePlan(
        Request $request,
        ?Plan $plan = null
    ): array {
        return $request->validate([
            'code' => [
                $plan ? 'sometimes' : 'required',
                'string',
                'max:100',
                Rule::unique('plans', 'code')->ignore($plan?->getKey()),
            ],
            'title' => [
                $plan ? 'sometimes' : 'required',
                'string',
                'max:255',
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'duration_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*.feature_id' => ['required', 'integer', 'exists:features,id'],
            'features.*.value' => ['nullable'],
        ]);
    }

    private function syncFeatures(
        Plan $plan,
        array $features
    ): void {
        $sync = [];

        foreach ($features as $item) {
            $sync[(int) $item['feature_id']] = [
                'value' => json_encode(
                    $item['value'] ?? true,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ];
        }

        $plan->features()->sync($sync);
    }

    private function authorizePlatform(Request $request): void
    {
        abort_unless(
            $request->user()
                && $this->permissions->allows(
                    $request->user(),
                    'reports.platform.view',
                    null
                ),
            403
        );
    }
}
