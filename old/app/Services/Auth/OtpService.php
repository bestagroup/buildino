<?php
namespace App\Services\Auth;

use App\Contracts\Auth\OtpSender;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes((int) config('auth_otp.ttl_minutes', 2)),
                'attempts' => 0,
                'request_ip' => $ip,
            ]);
        });

        $this->sender->send($identifier, $channel, $code);
    }

    public function verify(string $identifier, string $channel, string $purpose, string $code): OtpCode
    {
        return DB::transaction(function () use ($identifier, $channel, $purpose, $code): OtpCode {
            $otp = OtpCode::query()
                ->where('identifier', $identifier)
                ->where('channel', $channel)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp || $otp->expires_at->isPast()) {
                throw ValidationException::withMessages(['code' => 'OTP is invalid or expired.']);
            }

            if ($otp->attempts >= (int) config('auth_otp.max_attempts', 5)) {
                throw ValidationException::withMessages(['code' => 'Maximum OTP attempts exceeded.']);
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');
                throw ValidationException::withMessages(['code' => 'OTP is invalid or expired.']);
            }

            $otp->update(['verified_at' => now(), 'consumed_at' => now()]);
            return $otp->refresh();
        }, 3);
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
