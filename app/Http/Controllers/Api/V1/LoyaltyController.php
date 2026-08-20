<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimLoyaltyRewardRequest;
use App\Http\Requests\RejectLoyaltyRewardClaimRequest;
use App\Http\Requests\StoreLoyaltyRewardRequest;
use App\Http\Requests\StoreLoyaltyRuleRequest;
use App\Http\Resources\V1\LoyaltyRewardClaimResource;
use App\Http\Resources\V1\LoyaltyRewardResource;
use App\Http\Resources\V1\LoyaltyRuleResource;
use App\Http\Resources\V1\LoyaltyTransactionResource;
use App\Models\Building;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRewardClaim;
use App\Services\Loyalty\LoyaltyAccessService;
use App\Services\Loyalty\LoyaltyLedgerService;
use App\Services\Loyalty\LoyaltyRewardService;
use App\Services\Loyalty\LoyaltyRuleService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LoyaltyController extends Controller
{
    public function account(
        Request $request,
        LoyaltyLedgerService $ledger
    ): JsonResponse {
        $account = $ledger->accountFor($request->user());
        $transactions = $account->loyaltyTransactions()
            ->latest('id')
            ->paginate(
                min(max($request->integer('per_page', 20), 1), 100)
            )
            ->withQueryString();

        return response()->json([
            'data' => [
                'id' => $account->getKey(),
                'balance' => (int) $account->balance,
                'transactions' => LoyaltyTransactionResource::collection(
                    $transactions
                )->response()->getData(true),
            ],
        ]);
    }

    public function rewards(
        Request $request,
        LoyaltyAccessService $access
    ): AnonymousResourceCollection {
        $buildingIds = $access->residentBuildingIds($request->user());

        return LoyaltyRewardResource::collection(
            LoyaltyReward::query()
                ->where('is_active', true)
                ->where(function ($query) use ($buildingIds): void {
                    $query->whereNull('building_id');

                    if ($buildingIds->isNotEmpty()) {
                        $query->orWhereIn('building_id', $buildingIds->all());
                    }
                })
                ->orderBy('required_points')
                ->paginate(
                    min(max($request->integer('per_page', 20), 1), 100)
                )
                ->withQueryString()
        );
    }

    public function claims(Request $request): AnonymousResourceCollection
    {
        return LoyaltyRewardClaimResource::collection(
            LoyaltyRewardClaim::query()
                ->where('user_id', $request->user()->getKey())
                ->with('loyaltyReward')
                ->latest('id')
                ->paginate(
                    min(max($request->integer('per_page', 20), 1), 100)
                )
                ->withQueryString()
        );
    }

    public function claim(
        ClaimLoyaltyRewardRequest $request,
        LoyaltyReward $loyaltyReward,
        LoyaltyRewardService $service
    ): JsonResponse {
        $claim = $service->claim(
            $loyaltyReward,
            $request->user(),
            $request->validated('idempotency_key')
        );

        return (new LoyaltyRewardClaimResource(
            $claim->load('loyaltyReward')
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function buildingRules(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        $this->allow($permissions, $request, $building, 'view');

        return LoyaltyRuleResource::collection(
            $building->loyaltyRules()
                ->latest('id')
                ->paginate(
                    min(max($request->integer('per_page', 20), 1), 100)
                )
                ->withQueryString()
        );
    }

    public function storeRule(
        StoreLoyaltyRuleRequest $request,
        Building $building,
        PermissionChecker $permissions,
        LoyaltyRuleService $service
    ): JsonResponse {
        $this->allow($permissions, $request, $building, 'create');

        return (new LoyaltyRuleResource(
            $service->createVersion($building, $request->validated())
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function buildingRewards(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        $this->allow($permissions, $request, $building, 'view');

        return LoyaltyRewardResource::collection(
            $building->loyaltyRewards()
                ->latest('id')
                ->paginate(
                    min(max($request->integer('per_page', 20), 1), 100)
                )
                ->withQueryString()
        );
    }

    public function buildingClaims(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        $this->allow($permissions, $request, $building, 'view');

        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::in(['pending', 'approved', 'rejected', 'cancelled']),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return LoyaltyRewardClaimResource::collection(
            LoyaltyRewardClaim::query()
                ->whereHas(
                    'loyaltyReward',
                    fn ($query) => $query->where(
                        'building_id',
                        $building->getKey()
                    )
                )
                ->when(
                    $validated['status'] ?? null,
                    fn ($query, string $status) => $query->where(
                        'status',
                        $status
                    )
                )
                ->with(['loyaltyReward', 'user', 'processedBy'])
                ->latest('id')
                ->paginate((int) ($validated['per_page'] ?? 20))
                ->withQueryString()
        );
    }

    public function storeReward(
        StoreLoyaltyRewardRequest $request,
        Building $building,
        PermissionChecker $permissions
    ): JsonResponse {
        $this->allow($permissions, $request, $building, 'create');
        $reward = $building->loyaltyRewards()->create($request->validated());

        return (new LoyaltyRewardResource($reward))
            ->response()
            ->setStatusCode(201);
    }

    public function approveClaim(
        Request $request,
        LoyaltyRewardClaim $loyaltyRewardClaim,
        PermissionChecker $permissions,
        LoyaltyRewardService $service
    ): LoyaltyRewardClaimResource {
        $this->allowClaim(
            $permissions,
            $request,
            $loyaltyRewardClaim,
            'update'
        );

        return new LoyaltyRewardClaimResource(
            $service->approve($loyaltyRewardClaim, $request->user())
                ->load('loyaltyReward')
        );
    }

    public function rejectClaim(
        RejectLoyaltyRewardClaimRequest $request,
        LoyaltyRewardClaim $loyaltyRewardClaim,
        PermissionChecker $permissions,
        LoyaltyRewardService $service
    ): LoyaltyRewardClaimResource {
        $this->allowClaim(
            $permissions,
            $request,
            $loyaltyRewardClaim,
            'update'
        );

        return new LoyaltyRewardClaimResource(
            $service->reject(
                $loyaltyRewardClaim,
                $request->user(),
                $request->validated('reason')
            )->load('loyaltyReward')
        );
    }

    private function allow(
        PermissionChecker $permissions,
        Request $request,
        Building $building,
        string $action
    ): void {
        abort_unless(
            $permissions->allows(
                $request->user(),
                "loyalty-rewards.{$action}",
                $building
            ),
            403
        );
    }

    private function allowClaim(
        PermissionChecker $permissions,
        Request $request,
        LoyaltyRewardClaim $claim,
        string $action
    ): void {
        $claim->loadMissing('loyaltyReward.building');
        $building = $claim->loyaltyReward?->building;

        abort_unless(
            $building
            && $permissions->allows(
                $request->user(),
                "loyalty-rewards.{$action}",
                $building
            ),
            403
        );
    }
}
