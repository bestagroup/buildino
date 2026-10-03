<?php

namespace App\Http\Middleware;

use App\Services\Security\BuildingContextResolver;
use App\Services\Subscription\SubscriptionEntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionFeature
{
    public function __construct(
        private readonly BuildingContextResolver $resolver,
        private readonly SubscriptionEntitlementService $entitlements,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $feature
    ): Response {
        if (! config('subscriptions.enforce', false)) {
            return $next($request);
        }

        $building = $request->attributes->get('building_context')
            ?? $this->resolver->resolve($request);

        if (! $building) {
            return $next($request);
        }

        $request->attributes->set('building_context', $building);

        if (! $this->entitlements->featureEnabled($building, $feature)) {
            return response()->json([
                'success' => false,
                'message' => 'این قابلیت در پلن فعلی ساختمان فعال نیست.',
                'code' => 'FEATURE_NOT_AVAILABLE',
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}
