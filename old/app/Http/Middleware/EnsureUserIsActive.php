<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->is_active || $user->is_blocked) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Your account is inactive or blocked.',
            ], 403);
        }

        return $next($request);
    }
}
