<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ManagementLoginRequest;
use App\Http\Requests\Web\RequestWebOtpRequest;
use App\Http\Requests\Web\VerifyWebOtpRequest;
use App\Models\User;
use App\Services\Web\ManagementDashboardAccessService;
use App\Services\Web\WebOtpLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ManagementAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route(
                'management.dashboard'
            );
        }

        return view(
            'management.auth.login'
        );
    }

    public function store(
        ManagementLoginRequest $request,
        ManagementDashboardAccessService $access
    ): RedirectResponse {
        $data = $request->validated();

        $login = trim(
            (string) $data['login']
        );

        $column = filter_var(
            $login,
            FILTER_VALIDATE_EMAIL
        )
            ? 'email'
            : 'mobile';

        $user = User::query()
            ->where($column, $login)
            ->first();

        if (
            ! $user
            || ! $user->password
            || ! Hash::check(
                (string) $data['password'],
                $user->password
            )
        ) {
            return back()
                ->withInput(
                    $request->only('login')
                )
                ->withErrors([
                    'login' => 'اطلاعات ورود صحیح نیست.',
                ]);
        }

        if (
            ! $user->is_active
            || $user->is_blocked
        ) {
            return back()
                ->withInput(
                    $request->only('login')
                )
                ->withErrors([
                    'login' => 'حساب کاربری غیرفعال یا مسدود است.',
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
                    $request->only('login')
                )
                ->withErrors([
                    'login' => 'هویت این حساب هنوز تأیید نشده است.',
                ]);
        }

        if (! $access->hasAnyAccess($user)) {
            return back()
                ->withInput(
                    $request->only('login')
                )
                ->withErrors([
                    'login' => 'برای این حساب دسترسی داشبورد مدیریتی تعریف نشده است.',
                ]);
        }

        Auth::guard('web')->login(
            $user,
            (bool) ($data['remember'] ?? false)
        );

        $request
            ->session()
            ->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()
            ->intended(
                route(
                    'management.dashboard'
                )
            );
    }

    public function requestOtp(
        RequestWebOtpRequest $request,
        WebOtpLoginService $otp
    ): RedirectResponse {
        $mobile = (string) $request
            ->validated('mobile');

        $otp->request(
            $mobile,
            WebOtpLoginService::MANAGEMENT,
            $request->ip()
        );

        $request->session()->put(
            $otp->sessionKey(
                WebOtpLoginService::MANAGEMENT
            ),
            $mobile
        );

        return redirect()
            ->route('login')
            ->with('auth_method', 'otp')
            ->with(
                'otp_status',
                'اگر حساب مدیریتی واجد شرایط باشد، کد ورود برای شماره واردشده پیامک شد.'
            );
    }

    public function verifyOtp(
        VerifyWebOtpRequest $request,
        WebOtpLoginService $otp
    ): RedirectResponse {
        $sessionKey = $otp->sessionKey(
            WebOtpLoginService::MANAGEMENT
        );
        $mobile = (string) $request
            ->session()
            ->get($sessionKey, '');

        if ($mobile === '') {
            return redirect()
                ->route('login')
                ->with('auth_method', 'otp')
                ->withErrors([
                    'mobile' => 'ابتدا شماره موبایل را وارد و کد ورود را درخواست کنید.',
                ]);
        }

        $user = $otp->verify(
            $mobile,
            (string) $request->validated('code'),
            WebOtpLoginService::MANAGEMENT
        );

        $request->session()->forget($sessionKey);
        $otp->login($request, $user);

        return redirect()->intended(
            route('management.dashboard')
        );
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('web')->logout();

        request()
            ->session()
            ->invalidate();

        request()
            ->session()
            ->regenerateToken();

        return redirect()
            ->route('login');
    }
}
