<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ManagementPasswordResetWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_exposes_forgot_password_link(): void
    {
        $this->get(
            '/management/login'
        )
            ->assertOk()
            ->assertSee(
                'رمز عبور را فراموش کرده‌اید؟'
            )
            ->assertSee(
                route(
                    'password.request'
                ),
                false
            );
    }

    public function test_forgot_password_accepts_mobile_and_sends_reset_to_verified_email(): void
    {
        Notification::fake();

        $user =
            User::factory()
                ->create([
                    'mobile' =>
                        '09125550123',
                    'email' =>
                        'manager-reset@buildino.local',
                    'mobile_verified_at' =>
                        now(),
                    'email_verified_at' =>
                        now(),
                    'is_active' =>
                        true,
                    'is_blocked' =>
                        false,
                ]);

        $this->post(
            '/management/forgot-password',
            [
                'login' =>
                    $user->mobile,
            ]
        )
            ->assertRedirect()
            ->assertSessionHas(
                'status'
            );

        Notification::assertSentTo(
            $user,
            ResetPassword::class
        );
    }

    public function test_blocked_user_does_not_receive_password_reset_notification(): void
    {
        Notification::fake();

        $user =
            User::factory()
                ->create([
                    'email' =>
                        'blocked-reset@buildino.local',
                    'email_verified_at' =>
                        now(),
                    'is_active' =>
                        true,
                    'is_blocked' =>
                        true,
                ]);

        $this->post(
            '/management/forgot-password',
            [
                'login' =>
                    $user->email,
            ]
        )
            ->assertRedirect()
            ->assertSessionHas(
                'status'
            );

        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_password_and_redirects_to_login(): void
    {
        $user =
            User::factory()
                ->create([
                    'email' =>
                        'reset-success@buildino.local',
                    'email_verified_at' =>
                        now(),
                    'is_active' =>
                        true,
                    'is_blocked' =>
                        false,
                    'password' =>
                        Hash::make(
                            'OldPassword123'
                        ),
                ]);

        $token =
            Password::broker()
                ->createToken(
                    $user
                );

        $newPassword =
            'NewPassword1405';

        $this->post(
            '/management/reset-password',
            [
                'token' =>
                    $token,
                'email' =>
                    $user->email,
                'password' =>
                    $newPassword,
                'password_confirmation' =>
                    $newPassword,
            ]
        )
            ->assertRedirect(
                '/management/login'
            )
            ->assertSessionHas(
                'status'
            );

        $this->assertTrue(
            Hash::check(
                $newPassword,
                $user
                    ->refresh()
                    ->password
            )
        );

        $this->assertFalse(
            Password::broker()
                ->tokenExists(
                    $user->refresh(),
                    $token
                ),
            'A successfully used password-reset token must be consumed.'
        );
    }

    public function test_consumed_token_cannot_be_reused(): void
    {
        $user =
            User::factory()
                ->create([
                    'email' =>
                        'reset-replay@buildino.local',
                    'email_verified_at' =>
                        now(),
                    'is_active' =>
                        true,
                    'is_blocked' =>
                        false,
                    'password' =>
                        Hash::make(
                            'OriginalPass1405'
                        ),
                ]);

        $token =
            Password::broker()
                ->createToken(
                    $user
                );

        $this->post(
            '/management/reset-password',
            [
                'token' =>
                    $token,
                'email' =>
                    $user->email,
                'password' =>
                    'FirstPassword1405',
                'password_confirmation' =>
                    'FirstPassword1405',
            ]
        )->assertRedirect(
            '/management/login'
        );

        $this->from(
            '/management/reset-password/reused'
        )
            ->post(
                '/management/reset-password',
                [
                    'token' =>
                        $token,
                    'email' =>
                        $user->email,
                    'password' =>
                        'SecondPassword1405',
                    'password_confirmation' =>
                        'SecondPassword1405',
                ]
            )
            ->assertRedirect(
                '/management/reset-password/reused'
            )
            ->assertSessionHasErrors(
                'email'
            );

        $this->assertTrue(
            Hash::check(
                'FirstPassword1405',
                $user
                    ->refresh()
                    ->password
            )
        );
    }

    public function test_invalid_token_does_not_change_password(): void
    {
        $oldPassword =
            'OriginalPass1405';

        $user =
            User::factory()
                ->create([
                    'email' =>
                        'invalid-token@buildino.local',
                    'email_verified_at' =>
                        now(),
                    'is_active' =>
                        true,
                    'is_blocked' =>
                        false,
                    'password' =>
                        Hash::make(
                            $oldPassword
                        ),
                ]);

        $this->from(
            '/management/reset-password/invalid'
        )
            ->post(
                '/management/reset-password',
                [
                    'token' =>
                        'invalid-token',
                    'email' =>
                        $user->email,
                    'password' =>
                        'NewPassword1405',
                    'password_confirmation' =>
                        'NewPassword1405',
                ]
            )
            ->assertRedirect(
                '/management/reset-password/invalid'
            )
            ->assertSessionHasErrors(
                'email'
            );

        $this->assertTrue(
            Hash::check(
                $oldPassword,
                $user
                    ->refresh()
                    ->password
            )
        );
    }
}
