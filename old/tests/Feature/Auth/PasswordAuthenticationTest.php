<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_password(): void
    {
        User::factory()->create([
            'mobile' => '09121111111',
            'email' => null,
            'password' => Hash::make('StrongPassword123!'),
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $this->postJson('/api/v1/auth/password/login', [
            'login' => '09121111111',
            'password' => 'StrongPassword123!',
            'device_name' => 'phpunit',
        ])->assertOk()->assertJsonStructure(['data' => ['id'], 'access_token', 'token_type']);
    }

    public function test_blocked_user_cannot_login(): void
    {
        User::factory()->create([
            'mobile' => '09122222222',
            'email' => null,
            'password' => Hash::make('StrongPassword123!'),
            'is_active' => true,
            'is_blocked' => true,
        ]);

        $this->postJson('/api/v1/auth/password/login', [
            'login' => '09122222222',
            'password' => 'StrongPassword123!',
            'device_name' => 'phpunit',
        ])->assertUnprocessable();
    }
}
