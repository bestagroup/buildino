<?php

namespace App\Services\Web;

use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class WebOtpLoginService
{
    public const MANAGEMENT = 'management';

    public const PORTAL = 'portal';

    public function __construct(
        private readonly OtpService $otp,
        private readonly ManagementDashboardAccessService $management,
        private readonly PortalAccessService $portal
    ) {}

    public function request(
        string $mobile,
        string $area,
        ?string $ip = null
    ): void {
        $user = User::query()
            ->where('mobile', $mobile)
            ->first();

        /*
         * Keep the browser response generic while avoiding paid SMS delivery
         * for unknown, blocked, or out-of-area accounts.
         */
        if (! $user || ! $this->canEnter($user, $area)) {
            return;
        }

        $this->otp->request(
            $mobile,
            'sms',
            $this->purpose($area),
            $ip
        );
    }

    public function verify(
        string $mobile,
        string $code,
        string $area
    ): User {
        try {
            $this->otp->verify(
                $mobile,
                'sms',
                $this->purpose($area),
                $code
            );
        } catch (ValidationException $exception) {
            $maximumAttempts = str_contains(
                implode(' ', $exception->errors()['code'] ?? []),
                'Maximum OTP attempts'
            );

            throw ValidationException::withMessages([
                'code' => [
                    $maximumAttempts
                        ? 'تعداد تلاش‌های ورود بیش از حد مجاز است؛ کد جدیدی درخواست کنید.'
                        : 'کد تأیید نادرست یا منقضی شده است.',
                ],
            ]);
        }

        $user = User::query()
            ->where('mobile', $mobile)
            ->first();

        if (! $user || ! $this->canEnter($user, $area)) {
            throw ValidationException::withMessages([
                'code' => [
                    'کد تأیید معتبر نیست یا این حساب به بخش انتخاب‌شده دسترسی ندارد.',
                ],
            ]);
        }

        if ($user->mobile_verified_at === null) {
            $user->forceFill([
                'mobile_verified_at' => now(),
            ])->save();
        }

        return $user->fresh();
    }

    public function login(
        Request $request,
        User $user
    ): void {
        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
    }

    public function sessionKey(string $area): string
    {
        $this->assertArea($area);

        return "buildino.web_otp.{$area}.mobile";
    }

    private function canEnter(
        User $user,
        string $area
    ): bool {
        if (! $user->is_active || $user->is_blocked) {
            return false;
        }

        return match ($area) {
            self::MANAGEMENT => $this->management->hasAnyAccess($user),
            self::PORTAL => $this->portal->hasAnyAccess($user),
            default => false,
        };
    }

    private function purpose(string $area): string
    {
        $this->assertArea($area);

        return "web_{$area}_login";
    }

    private function assertArea(string $area): void
    {
        if (! in_array($area, [
            self::MANAGEMENT,
            self::PORTAL,
        ], true)) {
            throw new \InvalidArgumentException(
                'Unsupported web OTP login area.'
            );
        }
    }
}
