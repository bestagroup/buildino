<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthenticationService
{
    public function loginWithPassword(string $login, string $password, string $deviceName, Request $request): array
    {
        $column = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
        $user = User::query()->where($column, $login)->first();

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new ApiException('AUTH_INVALID_CREDENTIALS', 'The provided credentials are invalid.', 401);
        }

        $this->ensureAccountAllowed($user);
        $this->ensureIdentityVerified($user);

        return $this->issueToken($user, $deviceName, $request);
    }

    public function loginWithVerifiedOtp(string $identifier, string $channel, string $deviceName, Request $request): array
    {
        $column = $channel === 'email' ? 'email' : 'mobile';
        $user = User::query()->where($column, $identifier)->first();

        if (! $user) {
            throw new ApiException('AUTH_INVALID_CREDENTIALS', 'The provided credentials are invalid.', 401);
        }

        $this->ensureAccountAllowed($user);

        if ($channel === 'email' && ! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        if ($channel === 'sms' && ! $user->mobile_verified_at) {
            $user->forceFill(['mobile_verified_at' => now()])->save();
        }

        return $this->issueToken($user, $deviceName, $request);
    }

    private function issueToken(User $user, string $deviceName, Request $request): array
    {
        $this->ensureAccountAllowed($user);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token = $user->createToken($deviceName, ['api'])->plainTextToken;

        return ['user' => $user->fresh(), 'token' => $token, 'token_type' => 'Bearer'];
    }

    private function ensureAccountAllowed(User $user): void
    {
        if (! $user->is_active || $user->is_blocked) {
            throw new ApiException('AUTH_ACCOUNT_NOT_ALLOWED', 'This account is not allowed to sign in.', 403);
        }
    }

    private function ensureIdentityVerified(User $user): void
    {
        if (
            config('api_security.require_verified_identity', true)
            && ! $user->mobile_verified_at
            && ! $user->email_verified_at
        ) {
            throw new ApiException(
                'IDENTITY_VERIFICATION_REQUIRED',
                'A verified mobile number or email address is required.',
                403
            );
        }
    }
}
