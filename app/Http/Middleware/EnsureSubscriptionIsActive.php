<?php

namespace App\Http\Middleware;

use App\Services\Security\BuildingContextResolver;
use App\Services\Subscriptions\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsActive
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly BuildingContextResolver $resolver
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $building = $request->attributes->get('building_context')
            ?? $this->resolver->resolve($request);

        /*
         * Global API routes have no building context and remain available.
         * Building-scoped operations are entitlement-gated here.
         */
        if (! $building) {
            return $next($request);
        }

        $request->attributes->set('building_context', $building);

        $subscription = $this->subscriptions->usable($building);

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'code' => 'SUBSCRIPTION_INACTIVE',
                'message' => 'The building subscription is inactive, suspended or expired.',
            ], 403);
        }

        $request->attributes->set(
            'building_subscription',
            $subscription
        );

        return $next($request);
    }
}
