<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingSubscription;
use App\Models\Plan;
use App\Services\Security\BuildingAccessService;
use App\Services\Subscription\SubscriptionLifecycleService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly BuildingAccessService $buildingAccess,
        private readonly PermissionChecker $permissions,
        private readonly SubscriptionLifecycleService $lifecycle,
    ) {
    }

    public function index(
        Request $request,
        Building $building
    ): JsonResponse {
        $this->authorizeView($request, $building);

        $subscriptions = $building->buildingSubscriptions()
            ->with('plan:id,code,title,duration_days,price')
            ->latest('starts_at')
            ->paginate(
                min(max($request->integer('per_page', 25), 1), 100)
            );

        return response()->json($subscriptions);
    }

    public function show(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): JsonResponse {
        $building = $buildingSubscription->building()->firstOrFail();
        $this->authorizeView($request, $building);

        return response()->json([
            'data' => $buildingSubscription->load([
                'plan.features',
                'events' => fn ($query) => $query->latest()->limit(50),
            ]),
        ]);
    }

    public function store(
        Request $request,
        Building $building
    ): JsonResponse {
        $this->authorizeManage($request);

        $data = $this->validatedPayload($request);
        $plan = Plan::query()
            ->where('is_active', true)
            ->findOrFail($data['plan_id']);

        $subscription = $this->lifecycle->create(
            $building,
            $plan,
            $data,
            $request->user()
        );

        return response()->json([
            'data' => $subscription,
        ], 201);
    }

    public function renew(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): JsonResponse {
        $this->authorizeManage($request);

        $data = $this->validatedPayload($request, true);
        $plan = Plan::query()
            ->where('is_active', true)
            ->findOrFail($data['plan_id'] ?? $buildingSubscription->plan_id);

        $subscription = $this->lifecycle->renew(
            $buildingSubscription,
            $plan,
            $data,
            $request->user()
        );

        return response()->json([
            'data' => $subscription,
        ]);
    }

    public function suspend(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): JsonResponse {
        $this->authorizeManage($request);

        return response()->json([
            'data' => $this->lifecycle->transition(
                $buildingSubscription,
                SubscriptionStatus::Suspended,
                $request->user(),
                'suspended'
            ),
        ]);
    }

    public function resume(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): JsonResponse {
        $this->authorizeManage($request);

        return response()->json([
            'data' => $this->lifecycle->transition(
                $buildingSubscription,
                SubscriptionStatus::Active,
                $request->user(),
                'resumed'
            ),
        ]);
    }

    public function cancel(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): JsonResponse {
        $this->authorizeManage($request);

        return response()->json([
            'data' => $this->lifecycle->transition(
                $buildingSubscription,
                SubscriptionStatus::Cancelled,
                $request->user(),
                'cancelled'
            ),
        ]);
    }

    public function events(
        Request $request,
        BuildingSubscription $buildingSubscription
    ): JsonResponse {
        $building = $buildingSubscription->building()->firstOrFail();
        $this->authorizeView($request, $building);

        return response()->json([
            'data' => $buildingSubscription->events()
                ->with('actor:id,first_name,last_name,mobile')
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    private function validatedPayload(
        Request $request,
        bool $renewing = false
    ): array {
        return $request->validate([
            'plan_id' => [
                $renewing ? 'sometimes' : 'required',
                'integer',
                'exists:plans,id',
            ],
            'starts_at' => ['sometimes', 'date'],
            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
                'after:starts_at',
            ],
            'grace_ends_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:expires_at',
            ],
            'status' => [
                'sometimes',
                Rule::enum(SubscriptionStatus::class),
            ],
            'limits' => ['sometimes', 'nullable', 'array'],
        ]);
    }

    private function authorizeView(
        Request $request,
        Building $building
    ): void {
        abort_unless(
            $request->user()
                && (
                    $this->permissions->allows(
                        $request->user(),
                        'reports.platform.view',
                        null
                    )
                    || $this->buildingAccess->allows(
                        $request->user(),
                        $building
                    )
                ),
            403
        );
    }

    private function authorizeManage(Request $request): void
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
