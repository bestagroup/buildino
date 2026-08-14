<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ManagementForgotPasswordRequest;
use App\Http\Requests\Web\ManagementResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        | Only an active, unblocked account with a verified email receives
        | a reset link. The browser response stays identical for existing
        | and non-existing accounts.
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

        /*
        |--------------------------------------------------------------------------
        | Resolve the eligible account once
        |--------------------------------------------------------------------------
        |
        | We deliberately resolve the eligible User ourselves and then pass
        | that exact model to Laravel's token repository. This avoids a second
        | credential-based user lookup inside PasswordBroker::reset() while
        | preserving Laravel's standard hashed token and expiration checks.
        |
        */

        $user = User::query()
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
            ->first();

        if (! $user) {
            return $this->invalidResetResponse(
                'لینک بازنشانی معتبر نیست یا حساب امکان بازنشانی رمز عبور ندارد.'
            );
        }

        $broker =
            Password::broker();

        /*
        |--------------------------------------------------------------------------
        | Standard Laravel token validation
        |--------------------------------------------------------------------------
        |
        | tokenExists() delegates to the configured token repository, so
        | token hash verification and expiration semantics remain exactly
        | those configured by auth.passwords.users.
        |
        */

        if (
            ! $broker->tokenExists(
                $user,
                $data['token']
            )
        ) {
            return $this->invalidResetResponse(
                'لینک بازنشانی رمز عبور نامعتبر یا منقضی شده است.'
            );
        }

        DB::transaction(
            function () use (
                $broker,
                $data,
                $user
            ): void {
                /*
                 * User.password already has Laravel's "hashed" cast.
                 * Assigning the plain validated password lets the model
                 * hash it exactly once.
                 */
                $user->forceFill([
                    'password' =>
                        $data['password'],

                    'remember_token' =>
                        Str::random(
                            60
                        ),
                ])->save();

                /*
                 * Consume the token in the same database transaction so
                 * a successfully used link cannot be replayed.
                 */
                $broker->deleteToken(
                    $user
                );
            }
        );

        event(
            new PasswordReset(
                $user
            )
        );

        return redirect()
            ->route('login')
            ->with(
                'status',
                'رمز عبور با موفقیت تغییر کرد. اکنون می‌توانید با رمز جدید وارد شوید.'
            );
    }

    private function invalidResetResponse(
        string $message
    ): RedirectResponse {
        return back()
            ->withInput(
                request()->only(
                    'email'
                )
            )
            ->withErrors([
                'email' => $message,
            ]);
    }
}
