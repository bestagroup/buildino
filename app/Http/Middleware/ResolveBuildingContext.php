<?php

namespace App\Http\Middleware;

use App\Services\Security\BuildingContextResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBuildingContext
{
    public function __construct(
        private readonly BuildingContextResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $building = $this->resolver->resolve($request);

        if ($building) {
            $request->attributes->set('building_context', $building);
        }

        return $next($request);
    }
}
