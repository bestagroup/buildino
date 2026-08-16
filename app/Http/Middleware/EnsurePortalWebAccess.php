<?php

namespace App\Http\Middleware;

use App\Services\Web\PortalAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePortalWebAccess
{
    public function __construct(
        private readonly PortalAccessService $access
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        ?string $area = null
    ): Response {
        $user = $request->user();

        if (! $user) {
            return redirect()
                ->route(
                    'portal.login'
                );
        }

        if (
            ! $user->is_active
            || $user->is_blocked
        ) {
            $this->logout(
                $request
            );

            return redirect()
                ->route(
                    'portal.login'
                )
                ->withErrors([
                    'login' =>
                        'حساب کاربری غیرفعال یا مسدود است.',
                ]);
        }

        if (
            config(
                'api_security.require_verified_identity',
                true
            )
            && $user->mobile_verified_at === null
            && $user->email_verified_at === null
        ) {
            $this->logout(
                $request
            );

            return redirect()
                ->route(
                    'portal.login'
                )
                ->withErrors([
                    'login' =>
                        'برای ورود به پرتال، شماره موبایل یا ایمیل باید تأیید شده باشد.',
                ]);
        }

        $allowed = match ($area) {
            'resident' =>
                $this->access
                    ->hasResidentAccess(
                        $user
                    ),

            'provider' =>
                $this->access
                    ->hasProviderAccess(
                        $user
                    ),

            default =>
                $this->access
                    ->hasAnyAccess(
                        $user
                    ),
        };

        abort_unless(
            $allowed,
            403
        );

        return $next(
            $request
        );
    }

    private function logout(
        Request $request
    ): void {
        Auth::guard('web')
            ->logout();

        $request->session()
            ->invalidate();

        $request->session()
            ->regenerateToken();
    }
}
