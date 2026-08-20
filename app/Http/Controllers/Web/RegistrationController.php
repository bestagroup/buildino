<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RegisterRequest;
use App\Http\Requests\Web\VerifyRegistrationOtpRequest;
use App\Services\Auth\OtpService;
use App\Services\UnitInvitationService;
use App\Services\Web\SelfRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class RegistrationController extends Controller
{
    public function create(Request $request): View
    {
        $personas = config(
            'self_registration.personas',
            []
        );

        $requestedPersona = (string) $request
            ->query('persona', 'building_manager');

        $selectedPersona = array_key_exists(
            $requestedPersona,
            $personas
        )
            ? $requestedPersona
            : 'building_manager';

        return view('auth.register', [
            'personas' => $personas,
            'selectedPersona' => $selectedPersona,
            'invitationToken' => (string) $request->query(
                'invitation_token',
                ''
            ),
        ]);
    }

    public function store(
        RegisterRequest $request,
        SelfRegistrationService $registration,
        OtpService $otp
    ): RedirectResponse {
        $data = $request->validated();

        $registration
            ->assertRegistrationCanProceed(
                $data
            );

        $otp->request(
            (string) $data['mobile'],
            'sms',
            (string) config(
                'self_registration.otp_purpose',
                'registration'
            ),
            $request->ip()
        );

        $pending = Arr::only($data, [
            'persona',
            'first_name',
            'last_name',
            'mobile',
            'email',
            'complex_title',
            'building_title',
            'province',
            'city',
            'address',
            'postal_code',
            'invitation_token',
        ]);

        $pending['password_hash'] = Hash::make(
            (string) $data['password']
        );
        $pending['expires_at'] = now()
            ->addMinutes(15)
            ->timestamp;

        $request->session()->put(
            $this->pendingSessionKey(),
            $pending
        );

        return redirect()
            ->route('register.verify')
            ->with(
                'status',
                'کد تأیید برای شماره موبایل شما ارسال شد.'
            );
    }

    public function verifyForm(
        Request $request
    ): View|RedirectResponse {
        $pending = $this->pendingRegistration(
            $request
        );

        if (! $pending) {
            return redirect()
                ->route('register')
                ->withErrors([
                    'mobile' => 'فرآیند ثبت‌نام منقضی شده است؛ اطلاعات را دوباره وارد کنید.',
                ]);
        }

        return view('auth.verify-registration', [
            'maskedMobile' => $this->maskMobile(
                (string) $pending['mobile']
            ),
            'personaLabel' => config(
                'self_registration.personas.'
                    .$pending['persona'].'.label',
                'کاربر'
            ),
        ]);
    }

    public function verify(
        VerifyRegistrationOtpRequest $request,
        SelfRegistrationService $registration,
        OtpService $otp
    ): RedirectResponse {
        $pending = $this->pendingRegistration(
            $request
        );

        if (! $pending) {
            throw ValidationException::withMessages([
                'code' => [
                    'فرآیند ثبت‌نام منقضی شده است؛ اطلاعات را دوباره وارد کنید.',
                ],
            ]);
        }

        $otp->verify(
            (string) $pending['mobile'],
            'sms',
            (string) config(
                'self_registration.otp_purpose',
                'registration'
            ),
            (string) $request->validated('code')
        );

        $result = $registration->register(
            $pending
        );

        $user = $result['user'];

        Auth::guard('web')->login($user);

        $request->session()->forget(
            $this->pendingSessionKey()
        );
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()
            ->route($result['destination'])
            ->with(
                'status',
                'حساب شما با موفقیت ساخته شد؛ خوش آمدید.'
            );
    }

    public function resend(
        Request $request,
        OtpService $otp
    ): RedirectResponse {
        $pending = $this->pendingRegistration(
            $request
        );

        if (! $pending) {
            return redirect()
                ->route('register')
                ->withErrors([
                    'mobile' => 'فرآیند ثبت‌نام منقضی شده است؛ اطلاعات را دوباره وارد کنید.',
                ]);
        }

        $otp->request(
            (string) $pending['mobile'],
            'sms',
            (string) config(
                'self_registration.otp_purpose',
                'registration'
            ),
            $request->ip()
        );

        return back()->with(
            'status',
            'کد تأیید جدید ارسال شد.'
        );
    }

    public function invitation(
        Request $request,
        SelfRegistrationService $registration,
        UnitInvitationService $invitations
    ): RedirectResponse {
        $token = trim(
            (string) $request->query('token')
        );

        if (strlen($token) < 32) {
            return redirect()
                ->route('register', [
                    'persona' => 'tenant',
                ])
                ->withErrors([
                    'invitation_token' => 'لینک دعوت معتبر نیست.',
                ]);
        }

        $user = Auth::guard('web')->user();

        if ($user) {
            $invitations->accept(
                $token,
                $user
            );

            return redirect()
                ->route(
                    'portal.resident.dashboard'
                )
                ->with(
                    'status',
                    'دعوت واحد با موفقیت پذیرفته شد.'
                );
        }

        return redirect()->route('register', [
            'persona' => $registration
                ->personaForInvitation($token),
            'invitation_token' => $token,
        ]);
    }

    private function pendingRegistration(
        Request $request
    ): ?array {
        $pending = $request->session()->get(
            $this->pendingSessionKey()
        );

        if (
            ! is_array($pending)
            || empty($pending['mobile'])
            || empty($pending['persona'])
            || (int) ($pending['expires_at'] ?? 0)
                < now()->timestamp
        ) {
            $request->session()->forget(
                $this->pendingSessionKey()
            );

            return null;
        }

        return $pending;
    }

    private function pendingSessionKey(): string
    {
        return (string) config(
            'self_registration.pending_session_key',
            'buildino.pending_registration'
        );
    }

    private function maskMobile(string $mobile): string
    {
        if (strlen($mobile) < 7) {
            return $mobile;
        }

        return substr($mobile, 0, 4)
            .'***'
            .substr($mobile, -4);
    }
}
