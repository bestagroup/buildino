<?php

namespace App\Http\Middleware;

use App\Services\Security\BuildingContextResolver;
use App\Services\Subscription\SubscriptionEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsActive
{
    public function __construct(
        private readonly BuildingContextResolver $resolver,
        private readonly SubscriptionEntitlementService $entitlements,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('subscriptions.enforce', false)) {
            return $next($request);
        }

        $building = $request->attributes->get('building_context')
            ?? $this->resolver->resolve($request);

        /*
         * Subscription enforcement is intentionally contextual. Endpoints that
         * are not tied to a building (auth, global catalog, health, etc.) keep
         * working while building-scoped operations are gated.
         */
        if (! $building) {
            return $next($request);
        }

        $request->attributes->set('building_context', $building);

        if (! $this->entitlements->isUsable($building)) {
            return response()->json([
                'success' => false,
                'message' => 'اشتراک ساختمان غیرفعال یا منقضی شده است.',
                'code' => 'SUBSCRIPTION_INACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
