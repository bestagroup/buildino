<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_login_is_a_validation_error(): void
    {
        $this->postJson('/api/v1/auth/password/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['message', 'errors' => ['login', 'password', 'device_name']]);
    }

    public function test_incorrect_credentials_are_an_authentication_failure(): void
    {
        $user = $this->passwordUser();

        $this->postJson('/api/v1/auth/password/login', [
            'login' => $user->mobile,
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ])->assertUnauthorized()->assertExactJson([
            'code' => 'AUTH_INVALID_CREDENTIALS',
            'message' => 'The provided credentials are invalid.',
        ]);
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = $this->passwordUser(['is_blocked' => true]);

        $this->login($user)
            ->assertForbidden()
            ->assertJsonPath('code', 'AUTH_ACCOUNT_NOT_ALLOWED');

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_unverified_user_is_rejected_before_a_token_is_issued(): void
    {
        $user = $this->passwordUser([
            'mobile_verified_at' => null,
            'email_verified_at' => null,
        ]);

        $this->login($user)
            ->assertForbidden()
            ->assertJsonPath('code', 'IDENTITY_VERIFICATION_REQUIRED')
            ->assertJsonMissingPath('access_token');

        $this->assertCount(0, $user->tokens()->get());
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_user_can_login_with_stable_token_contract(): void
    {
        $user = $this->passwordUser();

        $response = $this->login($user)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['id'], 'access_token', 'token_type']);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertCount(1, $user->tokens()->get());
    }

    public function test_unauthenticated_protected_endpoint_uses_standard_error(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_permission_denial_uses_standard_forbidden_error(): void
    {
        Route::middleware('auth:sanctum')->get(
            'api/v1/test-forbidden-contract',
            fn () => throw new AuthorizationException
        );

        Sanctum::actingAs(User::factory()->create(), ['api']);

        $this->getJson('/api/v1/test-forbidden-contract')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_rate_limited_request_uses_standard_error(): void
    {
        config()->set('api_security.auth_rate_limit', 1);
        RateLimiter::clear('contract-rate-limit-user');

        $payload = [
            'login' => 'contract-rate-limit-user',
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ];

        $this->postJson('/api/v1/auth/password/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/password/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    private function passwordUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'mobile' => '09121111111',
            'email' => null,
            'mobile_verified_at' => now(),
            'email_verified_at' => null,
            'password' => Hash::make('StrongPassword123!'),
            'is_active' => true,
            'is_blocked' => false,
        ], $overrides));
    }

    private function login(User $user)
    {
        return $this->postJson('/api/v1/auth/password/login', [
            'login' => $user->mobile,
            'password' => 'StrongPassword123!',
            'device_name' => 'phpunit',
        ]);
    }
}
