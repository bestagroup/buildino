<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ManagementLoginRequest;
use App\Models\User;
use App\Services\Web\ManagementDashboardAccessService;
use App\Services\Web\PortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class PortalAuthController extends Controller
{
    public function create(
        PortalAccessService $access,
        ManagementDashboardAccessService $management
    ): View|RedirectResponse {
        $user =
            Auth::guard('web')
                ->user();

        if ($user) {
            if (
                $access->hasAnyAccess(
                    $user
                )
            ) {
                return redirect()
                    ->route(
                        'portal.dashboard'
                    );
            }

            if (
                $management
                    ->hasAnyAccess(
                        $user
                    )
            ) {
                return redirect()
                    ->route(
                        'management.dashboard'
                    );
            }
        }

        return view(
            'portal.auth.login'
        );
    }

    public function store(
        ManagementLoginRequest $request,
        PortalAccessService $access
    ): RedirectResponse {
        $data =
            $request->validated();

        $login = trim(
            (string) $data[
                'login'
            ]
        );

        $column =
            filter_var(
                $login,
                FILTER_VALIDATE_EMAIL
            )
                ? 'email'
                : 'mobile';

        $user =
            User::query()
                ->where(
                    $column,
                    $login
                )
                ->first();

        if (
            ! $user
            || ! $user->password
            || ! Hash::check(
                (string) $data[
                    'password'
                ],
                $user->password
            )
        ) {
            return back()
                ->withInput(
                    $request->only(
                        'login'
                    )
                )
                ->withErrors([
                    'login' =>
                        'اطلاعات ورود صحیح نیست.',
                ]);
        }

        if (
            ! $user->is_active
            || $user->is_blocked
        ) {
            return back()
                ->withInput(
                    $request->only(
                        'login'
                    )
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
            return back()
                ->withInput(
                    $request->only(
                        'login'
                    )
                )
                ->withErrors([
                    'login' =>
                        'هویت این حساب هنوز تأیید نشده است.',
                ]);
        }

        if (
            ! $access
                ->hasAnyAccess(
                    $user
                )
        ) {
            return back()
                ->withInput(
                    $request->only(
                        'login'
                    )
                )
                ->withErrors([
                    'login' =>
                        'برای این حساب دسترسی پرتال ساکنین یا ارائه‌دهندگان خدمات تعریف نشده است.',
                ]);
        }

        Auth::guard('web')
            ->login(
                $user,
                (bool) (
                    $data[
                        'remember'
                    ] ?? false
                )
            );

        $request->session()
            ->regenerate();

        $user->forceFill([
            'last_login_at' =>
                now(),
            'last_login_ip' =>
                $request->ip(),
        ])->save();

        return redirect()
            ->intended(
                route(
                    'portal.dashboard'
                )
            );
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('web')
            ->logout();

        request()
            ->session()
            ->invalidate();

        request()
            ->session()
            ->regenerateToken();

        return redirect()
            ->route(
                'portal.login'
            );
    }
}
