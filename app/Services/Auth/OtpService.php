<?php

namespace App\Services\Auth;

use App\Contracts\Auth\OtpSender;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private readonly OtpSender $sender) {}

    public function request(
        string $identifier,
        string $channel,
        string $purpose,
        ?string $ip = null
    ): void {
        $this->validateChannelIdentifier(
            $identifier,
            $channel
        );

        $user = $this->findUser(
            $identifier,
            $channel
        );

        /*
         * Login OTP endpoints must not become an arbitrary SMS/email relay.
         * Callers keep a generic response, while unknown/blocked identities
         * receive no provider delivery.
         */
        if (
            $this->requiresExistingUser($purpose)
            && (
                ! $user
                || ! $user->is_active
                || $user->is_blocked
            )
        ) {
            return;
        }

        $lock = Cache::lock(
            'otp-request:'.hash(
                'sha256',
                $channel.'|'.$purpose.'|'.$identifier
            ),
            max(
                5,
                (int) config(
                    'auth_otp.request_lock_seconds',
                    10
                )
            )
        );

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'identifier' =>
                    'برای درخواست دوباره کد تأیید، کمی صبر کنید.',
            ]);
        }

        try {
            $recent = OtpCode::query()
                ->where('identifier', $identifier)
                ->where('channel', $channel)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->where(
                    'created_at',
                    '>=',
                    now()->subSeconds(
                        (int) config(
                            'auth_otp.resend_after',
                            60
                        )
                    )
                )
                ->exists();

            if ($recent) {
                throw ValidationException::withMessages([
                    'identifier' =>
                        'برای درخواست دوباره کد تأیید، کمی صبر کنید.',
                ]);
            }

            $code = $this->generateCode();

            $otp = DB::transaction(
                function () use (
                    $user,
                    $identifier,
                    $channel,
                    $purpose,
                    $ip,
                    $code
                ): OtpCode {
                    OtpCode::query()
                        ->where(
                            'identifier',
                            $identifier
                        )
                        ->where(
                            'channel',
                            $channel
                        )
                        ->where(
                            'purpose',
                            $purpose
                        )
                        ->whereNull(
                            'consumed_at'
                        )
                        ->update([
                            'consumed_at' => now(),
                        ]);

                    return OtpCode::query()->create([
                        'user_id' => $user?->getKey(),
                        'identifier' => $identifier,
                        'channel' => $channel,
                        'purpose' => $purpose,
                        'code_hash' => Hash::make($code),
                        'expires_at' =>
                            now()->addMinutes(
                                (int) config(
                                    'auth_otp.ttl_minutes',
                                    2
                                )
                            ),
                        'attempts' => 0,
                        'request_ip' => $ip,
                    ]);
                },
                3
            );
        } finally {
            $lock->release();
        }

        try {
            $this->sender->send(
                $identifier,
                $channel,
                $code
            );
        } catch (Throwable $exception) {
            /*
             * A provider failure must not leave an undelivered code active
             * or block the user from immediately requesting a replacement.
             */
            OtpCode::query()
                ->whereKey($otp->getKey())
                ->whereNull('consumed_at')
                ->update([
                    'consumed_at' => now(),
                ]);

            throw $exception;
        }
    }

    public function verify(string $identifier, string $channel, string $purpose, string $code): OtpCode
    {
        $result = DB::transaction(function () use ($identifier, $channel, $purpose, $code): array {
            $otp = OtpCode::query()
                ->where('identifier', $identifier)
                ->where('channel', $channel)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp || $otp->expires_at->isPast()) {
                return [
                    'status' => 'invalid',
                    'otp' => null,
                ];
            }

            if ($otp->attempts >= (int) config('auth_otp.max_attempts', 5)) {
                return [
                    'status' => 'maximum_attempts',
                    'otp' => null,
                ];
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');

                return [
                    'status' => 'invalid',
                    'otp' => null,
                ];
            }

            $otp->update([
                'verified_at' => now(),
                'consumed_at' => now(),
            ]);

            return [
                'status' => 'verified',
                'otp' => $otp->refresh(),
            ];
        }, 3);

        if ($result['status'] === 'maximum_attempts') {
            throw ValidationException::withMessages([
                'code' => 'تعداد تلاش‌های مجاز تمام شده است؛ کد تأیید جدید درخواست کنید.',
            ]);
        }

        if ($result['status'] !== 'verified') {
            throw ValidationException::withMessages([
                'code' => 'کد تأیید نادرست است یا اعتبار آن تمام شده است.',
            ]);
        }

        return $result['otp'];
    }

    private function requiresExistingUser(
        string $purpose
    ): bool {
        return $purpose === 'login'
            || str_ends_with(
                $purpose,
                '_login'
            );
    }

    private function findUser(string $identifier, string $channel): ?User
    {
        return User::query()
            ->where($channel === 'email' ? 'email' : 'mobile', $identifier)
            ->first();
    }

    private function validateChannelIdentifier(string $identifier, string $channel): void
    {
        if (! in_array($channel, ['sms', 'email'], true)) {
            throw ValidationException::withMessages([
                'channel' => 'روش ارسال کد تأیید پشتیبانی نمی‌شود.',
            ]);
        }

        if ($channel === 'email' && ! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'identifier' => 'یک نشانی ایمیل معتبر وارد کنید.',
            ]);
        }
    }

    private function generateCode(): string
    {
        $digits = max(4, min((int) config('auth_otp.digits', 6), 8));
        $max = (10 ** $digits) - 1;

        return str_pad(
            (string) random_int(0, $max),
            $digits,
            '0',
            STR_PAD_LEFT
        );
    }
}
