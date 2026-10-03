<?php

namespace App\Http\Middleware;

use App\Services\Subscriptions\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsActive
{
    public function __construct(
        private readonly SubscriptionService $subscriptions
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $building = $request->attributes->get('building_context');

        if (! $building) {
            return response()->json([
                'success' => false,
                'code' => 'BUILDING_CONTEXT_REQUIRED',
                'message' => 'Building context is required.',
            ], 422);
        }

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
