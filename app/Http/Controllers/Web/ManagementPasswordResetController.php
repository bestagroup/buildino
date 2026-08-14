<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ManagementForgotPasswordRequest;
use App\Http\Requests\Web\ManagementResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ManagementPasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view(
            'management.auth.forgot-password'
        );
    }

    public function sendResetLink(
        ManagementForgotPasswordRequest $request
    ): RedirectResponse {
        $login = trim(
            (string) $request->validated(
                'login'
            )
        );

        $column = filter_var(
            $login,
            FILTER_VALIDATE_EMAIL
        )
            ? 'email'
            : 'mobile';

        $user = User::query()
            ->where(
                $column,
                $login
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Enumeration-safe behavior
        |--------------------------------------------------------------------------
        |
        | We only issue a reset token for an active, unblocked account whose
        | email address is verified. The browser receives the same message
        | whether the account exists or not.
        |
        */

        if (
            $user
            && $user->is_active
            && ! $user->is_blocked
            && $user->email
            && $user->email_verified_at
        ) {
            try {
                Password::broker()
                    ->sendResetLink([
                        'email' =>
                            $user->email,
                    ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return back()->with(
            'status',
            'اگر حساب فعال و دارای ایمیل تأییدشده باشد، لینک بازنشانی رمز عبور برای آن ارسال می‌شود.'
        );
    }

    public function resetForm(
        string $token
    ): View {
        return view(
            'management.auth.reset-password',
            [
                'token' => $token,
                'email' =>
                    request()->query(
                        'email',
                        ''
                    ),
            ]
        );
    }

    public function reset(
        ManagementResetPasswordRequest $request
    ): RedirectResponse {
        $data =
            $request->validated();

        $eligible = User::query()
            ->where(
                'email',
                $data['email']
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'is_blocked',
                false
            )
            ->whereNotNull(
                'email_verified_at'
            )
            ->exists();

        if (! $eligible) {
            return back()
                ->withInput(
                    $request->only(
                        'email'
                    )
                )
                ->withErrors([
                    'email' =>
                        'لینک بازنشانی معتبر نیست یا حساب امکان بازنشانی رمز عبور ندارد.',
                ]);
        }

        $status =
            Password::broker()
                ->reset(
                    [
                        'email' =>
                            $data['email'],

                        'password' =>
                            $data['password'],

                        'password_confirmation' =>
                            $data[
                                'password_confirmation'
                            ],

                        'token' =>
                            $data['token'],
                    ],
                    function (
                        User $user,
                        string $password
                    ): void {
                        $user->forceFill([
                            'password' =>
                                Hash::make(
                                    $password
                                ),

                            'remember_token' =>
                                Str::random(
                                    60
                                ),
                        ])->save();

                        event(
                            new PasswordReset(
                                $user
                            )
                        );
                    }
                );

        if (
            $status
            !== Password::PASSWORD_RESET
        ) {
            return back()
                ->withInput(
                    $request->only(
                        'email'
                    )
                )
                ->withErrors([
                    'email' =>
                        $this->statusMessage(
                            $status
                        ),
                ]);
        }

        return redirect()
            ->route('login')
            ->with(
                'status',
                'رمز عبور با موفقیت تغییر کرد. اکنون می‌توانید با رمز جدید وارد شوید.'
            );
    }

    private function statusMessage(
        string $status
    ): string {
        return match ($status) {
            Password::INVALID_TOKEN =>
                'لینک بازنشانی رمز عبور نامعتبر یا منقضی شده است.',

            Password::INVALID_USER =>
                'حساب کاربری معتبر پیدا نشد.',

            default =>
                'بازنشانی رمز عبور انجام نشد. لطفاً دوباره درخواست لینک کنید.',
        };
    }
}
