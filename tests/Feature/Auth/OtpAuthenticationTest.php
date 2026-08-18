<?php

namespace Tests\Feature\Auth;

use App\Contracts\Auth\OtpSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_can_be_requested_for_existing_mobile_user(): void
    {
        User::factory()->create(['mobile' => '09120000000', 'email' => null]);

        $fake = new class implements OtpSender
        {
            public array $sent = [];

            public function send(string $identifier, string $channel, string $code): void
            {
                $this->sent[] = compact('identifier', 'channel', 'code');
            }
        };
        $this->app->instance(OtpSender::class, $fake);

        $this->postJson('/api/v1/auth/otp/request', [
            'identifier' => '09120000000',
            'channel' => 'sms',
        ])->assertAccepted();

        $this->assertCount(1, $fake->sent);
        $this->assertDatabaseHas('otp_codes', ['identifier' => '09120000000', 'purpose' => 'login']);
    }

    public function test_successful_otp_login_uses_the_same_token_contract(): void
    {
        $user = User::factory()->create([
            'mobile' => '09123334444',
            'email' => null,
            'mobile_verified_at' => null,
            'email_verified_at' => null,
            'password' => Hash::make('unused-password'),
        ]);

        $fake = new class implements OtpSender
        {
            public string $code = '';

            public function send(string $identifier, string $channel, string $code): void
            {
                $this->code = $code;
            }
        };
        $this->app->instance(OtpSender::class, $fake);

        $this->postJson('/api/v1/auth/otp/request', [
            'identifier' => $user->mobile,
            'channel' => 'sms',
        ])->assertAccepted();

        $response = $this->postJson('/api/v1/auth/otp/login', [
            'identifier' => $user->mobile,
            'channel' => 'sms',
            'code' => $fake->code,
            'device_name' => 'phpunit-otp',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['id'], 'access_token', 'token_type']);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertNotNull($user->fresh()->mobile_verified_at);
    }
}
