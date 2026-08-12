<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('api_security.require_verified_identity', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $verified = $user->mobile_verified_at !== null
            || $user->email_verified_at !== null;

        if (! $verified) {
            return response()->json([
                'message' => 'A verified mobile number or email address is required.',
            ], 403);
        }

        return $next($request);
    }
}
