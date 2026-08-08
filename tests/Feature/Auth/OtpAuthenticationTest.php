<?php
namespace Tests\Feature\Auth;

use App\Contracts\Auth\OtpSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_can_be_requested_for_existing_mobile_user(): void
    {
        User::factory()->create(['mobile' => '09120000000', 'email' => null]);

        $fake = new class implements OtpSender {
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
}
