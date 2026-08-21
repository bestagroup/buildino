<?php

namespace App\Services\Auth;

use App\Contracts\Auth\OtpSender;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private readonly OtpSender $sender) {}

    public function request(string $identifier, string $channel, string $purpose, ?string $ip = null): void
    {
        $this->validateChannelIdentifier($identifier, $channel);

        $recent = OtpCode::query()
            ->where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subSeconds((int) config('auth_otp.resend_after', 60)))
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages(['identifier' => 'Please wait before requesting another code.']);
        }

        $user = $this->findUser($identifier, $channel);
        $code = $this->generateCode();

        DB::transaction(function () use ($user, $identifier, $channel, $purpose, $ip, $code): void {
            OtpCode::query()
                ->where('identifier', $identifier)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            OtpCode::query()->create([
                'user_id' => $user?->getKey(),
                'identifier' => $identifier,
                'channel' => $channel,
                'purpose' => $purpose,
                'code' => $code,
                'expires_at' => now()->addMinutes((int) config('auth_otp.ttl_minutes', 2)),
                'attempts' => 0,
                'request_ip' => $ip,
            ]);
        });

        $this->sender->send($identifier, $channel, $code);
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

            if (! hash_equals((string) $otp->code, $code)) {
                $otp->increment('attempts');

                return [
                    'status' => 'invalid',
                    'otp' => null,
                ];
            }

            $otp->update(['verified_at' => now(), 'consumed_at' => now()]);

            return [
                'status' => 'verified',
                'otp' => $otp->refresh(),
            ];
        }, 3);

        if ($result['status'] === 'maximum_attempts') {
            throw ValidationException::withMessages([
                'code' => 'Maximum OTP attempts exceeded.',
            ]);
        }

        if ($result['status'] !== 'verified') {
            throw ValidationException::withMessages([
                'code' => 'OTP is invalid or expired.',
            ]);
        }

        return $result['otp'];
    }

    private function findUser(string $identifier, string $channel): ?User
    {
        return User::query()->where($channel === 'email' ? 'email' : 'mobile', $identifier)->first();
    }

    private function validateChannelIdentifier(string $identifier, string $channel): void
    {
        if (! in_array($channel, ['sms', 'email'], true)) {
            throw ValidationException::withMessages(['channel' => 'Unsupported OTP channel.']);
        }

        if ($channel === 'email' && ! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['identifier' => 'A valid email address is required.']);
        }
    }

    private function generateCode(): string
    {
        $digits = max(4, min((int) config('auth_otp.digits', 6), 8));
        $max = (10 ** $digits) - 1;

        return str_pad((string) random_int(0, $max), $digits, '0', STR_PAD_LEFT);
    }
}
