<?php

namespace App\Http\Middleware;

use App\Services\Security\BuildingAccessService;
use App\Services\Security\BuildingContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuildingAccess
{
    public function __construct(
        private readonly BuildingContextResolver $resolver,
        private readonly BuildingAccessService $access,
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $building = $request->attributes->get(
            'building_context'
        ) ?? $this->resolver->resolve($request);

        if (! $building) {
            return response()->json([
                'success' => false,
                'message' => 'Building context could not be resolved.',
                'code' => 'BUILDING_CONTEXT_REQUIRED',
            ], 422);
        }

        $user = $request->user();

        if (! $user || ! $this->access->allows($user, $building)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this building.',
                'code' => 'BUILDING_ACCESS_DENIED',
            ], 403);
        }

        $request->attributes->set(
            'building_context',
            $building
        );

        return $next($request);
    }
}
