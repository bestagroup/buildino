<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInitialPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(
            'password.first-change',
            'password.first-change.update',
            'management.logout',
            'portal.logout',
            'password.request',
            'password.email',
            'password.reset',
            'password.update'
        )) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'code' => 'PASSWORD_CHANGE_REQUIRED',
                'message' => 'برای ادامه، ابتدا رمز عبور اولیه حساب را تغییر دهید.',
                'redirect' => route('password.first-change'),
            ], 403);
        }

        return redirect()->route('password.first-change');
    }
}
