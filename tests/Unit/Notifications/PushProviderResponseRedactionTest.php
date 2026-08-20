<?php

namespace Tests\Unit\Notifications;

use App\Contracts\Notifications\PushSender;
use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\NotificationMessage;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushProviderResponseRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_response_cannot_persist_raw_device_token(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'is_active' => true,
            'is_blocked' => false,
        ]);

        UserDevice::query()->create([
            'user_id' => $user->getKey(),
            'device_id' => 'redaction-device',
            'platform' => 'android',
            'push_token' => 'raw-provider-echo-token',
            'last_used_at' => now(),
        ]);

        $push = new class implements PushSender {
            public function send(
                array $tokens,
                string $title,
                string $message,
                array $data = []
            ): array {
                return [
                    'provider' => 'http',
                    'accepted' => true,
                    'response' => [
                        'debug' =>
                            'provider echoed token: '.$tokens[0],
                    ],
                ];
            }
        };

        $sms = new class implements SmsSender {
            public function send(
                string $mobile,
                string $message
            ): array {
                return ['accepted' => true];
            }
        };

        $this->app->instance(PushSender::class, $push);
        $this->app->instance(SmsSender::class, $sms);

        app(UserNotificationService::class)->send(
            $user,
            new NotificationMessage(
                'test.push.redaction',
                'Buildino',
                'Security boundary test'
            ),
            'push',
            'push-provider-redaction-test'
        );

        $log = NotificationLog::query()
            ->where(
                'idempotency_key',
                'push-provider-redaction-test'
            )
            ->firstOrFail();

        $serialized = json_encode(
            $log->response,
            JSON_UNESCAPED_SLASHES
        );

        $this->assertStringNotContainsString(
            'raw-provider-echo-token',
            $serialized
        );

        $this->assertStringContainsString(
            '[REDACTED_DEVICE_TOKEN]',
            $serialized
        );
    }
}
