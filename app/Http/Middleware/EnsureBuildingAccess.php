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

    public function handle(Request $request, Closure $next): Response
    {
        $building = $request->attributes->get('building_context')
            ?? $this->resolver->resolve($request);

        if (! $building) {
            return response()->json([
                'message' => 'Building context could not be resolved.',
            ], 422);
        }

        if (! $this->access->allows($request->user(), $building)) {
            return response()->json([
                'message' => 'You do not have access to this building.',
            ], 403);
        }

        $request->attributes->set('building_context', $building);

        return $next($request);
    }
}
