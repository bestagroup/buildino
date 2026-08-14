<?php

namespace App\Http\Middleware;

use App\Services\Web\ManagementDashboardAccessService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagementWebAccess
{
    public function __construct(
        private readonly ManagementDashboardAccessService $access
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (
            ! $user->is_active
            || $user->is_blocked
        ) {
            $this->logout($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'login' =>
                        'حساب کاربری غیرفعال یا مسدود است.',
                ]);
        }

        $requiresVerifiedIdentity =
            (bool) config(
                'api_security.require_verified_identity',
                true
            );

        if (
            $requiresVerifiedIdentity
            && $user->mobile_verified_at === null
            && $user->email_verified_at === null
        ) {
            $this->logout($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'login' =>
                        'برای ورود به پنل مدیریتی، شماره موبایل یا ایمیل باید تأیید شده باشد.',
                ]);
        }

        if (! $this->access->hasAnyAccess($user)) {
            $this->logout($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'login' =>
                        'برای این حساب دسترسی داشبورد مدیریتی تعریف نشده است.',
                ]);
        }

        return $next($request);
    }

    private function logout(
        Request $request
    ): void {
        Auth::guard('web')->logout();

        $request
            ->session()
            ->invalidate();

        $request
            ->session()
            ->regenerateToken();
    }
}
