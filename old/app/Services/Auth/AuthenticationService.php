<?php
namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function loginWithPassword(string $login, string $password, string $deviceName, Request $request): array
    {
        $column = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
        $user = User::query()->where($column, $login)->first();

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['login' => 'Invalid credentials.']);
        }

        return $this->issueToken($user, $deviceName, $request);
    }

    public function loginWithVerifiedOtp(string $identifier, string $channel, string $deviceName, Request $request): array
    {
        $column = $channel === 'email' ? 'email' : 'mobile';
        $user = User::query()->where($column, $identifier)->first();

        if (! $user) {
            throw ValidationException::withMessages(['identifier' => 'No active account exists for this identifier.']);
        }

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
        if (! $user->is_active || $user->is_blocked) {
            throw ValidationException::withMessages(['login' => 'This account is not allowed to sign in.']);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token = $user->createToken($deviceName, ['api'])->plainTextToken;

        return ['user' => $user->fresh(), 'token' => $token, 'token_type' => 'Bearer'];
    }
}
