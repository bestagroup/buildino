<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $building = $request->attributes->get('building_context');

        if (! $building) {
            return response()->json([
                'message' => 'Building context is required.',
            ], 422);
        }

        $active = $building->buildingSubscriptions()
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if (! $active) {
            return response()->json([
                'message' => 'The building subscription is inactive or expired.',
            ], 403);
        }

        return $next($request);
    }
}
