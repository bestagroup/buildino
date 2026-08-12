<?php

namespace Tests\Unit\Notifications;

use App\Contracts\Notifications\PushSender;
use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\NotificationMessage;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotency_key_prevents_duplicate_successful_notification(): void
    {
        $user = User::factory()->create([
            'mobile' => '09120000001',
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $sms = new class implements SmsSender {
            public int $calls = 0;
            public function send(string $mobile, string $message): array
            {
                $this->calls++;
                return ['accepted' => true];
            }
        };

        $push = new class implements PushSender {
            public function send(array $tokens, string $title, string $message, array $data = []): array
            {
                return ['accepted' => true];
            }
        };

        $this->app->instance(SmsSender::class, $sms);
        $this->app->instance(PushSender::class, $push);

        $service = app(UserNotificationService::class);
        $message = new NotificationMessage('test.sms', 'Test', 'Message');

        $service->send($user, $message, 'sms', 'unique-notification-key');
        $service->send($user, $message, 'sms', 'unique-notification-key');

        $this->assertSame(1, $sms->calls);
        $this->assertSame(
            1,
            NotificationLog::query()->where('idempotency_key', 'unique-notification-key')->count()
        );
    }
}
