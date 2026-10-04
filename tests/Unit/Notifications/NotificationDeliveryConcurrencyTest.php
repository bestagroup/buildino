<?php

namespace Tests\Unit\Notifications;

use App\Contracts\Notifications\PushSender;
use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\NotificationMessage;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\UserNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NotificationDeliveryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_processing_notification_cannot_be_delivered_by_a_second_worker(): void
    {
        $user = $this->activeUser();
        $sms = $this->bindSenders();

        NotificationLog::query()->create([
            'idempotency_key' => 'notification-concurrent-claim',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notification_type' => 'test.sms',
            'channel' => 'sms',
            'provider' => 'test',
            'title' => 'Buildino',
            'message' => 'Concurrent delivery',
            'status' => NotificationStatus::Processing,
            'attempts' => 1,
            'last_attempt_at' => now(),
        ]);

        $result = app(UserNotificationService::class)->send(
            $user,
            new NotificationMessage(
                'test.sms',
                'Buildino',
                'Concurrent delivery'
            ),
            'sms',
            'notification-concurrent-claim'
        );

        $this->assertNotNull($result);
        $this->assertSame(0, $sms->calls);
        $this->assertSame(
            NotificationStatus::Processing,
            $result->status
        );
        $this->assertSame(1, $result->attempts);
    }

    public function test_stale_processing_notification_can_be_reclaimed_after_worker_failure(): void
    {
        config([
            'notifications.processing_stale_seconds' => 60,
        ]);

        $user = $this->activeUser();
        $sms = $this->bindSenders();

        NotificationLog::query()->create([
            'idempotency_key' => 'notification-stale-claim',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notification_type' => 'test.sms',
            'channel' => 'sms',
            'provider' => 'test',
            'title' => 'Buildino',
            'message' => 'Stale delivery',
            'status' => NotificationStatus::Processing,
            'attempts' => 1,
            'last_attempt_at' => now()->subMinutes(5),
        ]);

        $result = app(UserNotificationService::class)->send(
            $user,
            new NotificationMessage(
                'test.sms',
                'Buildino',
                'Stale delivery'
            ),
            'sms',
            'notification-stale-claim'
        );

        $this->assertNotNull($result);
        $this->assertSame(1, $sms->calls);
        $this->assertSame(
            NotificationStatus::Sent,
            $result->status
        );
        $this->assertSame(2, $result->attempts);
    }

    public function test_idempotency_key_cannot_be_reused_for_another_recipient(): void
    {
        $firstUser = $this->activeUser(
            '09129990111'
        );
        $secondUser = $this->activeUser(
            '09129990112'
        );
        $sms = $this->bindSenders();
        $service = app(
            UserNotificationService::class
        );

        $message = new NotificationMessage(
            'test.sms',
            'Buildino',
            'Idempotency binding'
        );

        $first = $service->send(
            $firstUser,
            $message,
            'sms',
            'notification-recipient-binding'
        );

        $this->assertNotNull($first);
        $this->assertSame(1, $sms->calls);

        try {
            $service->send(
                $secondUser,
                $message,
                'sms',
                'notification-recipient-binding'
            );

            $this->fail(
                'Notification idempotency key collision was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'idempotency_key',
                $exception->errors()
            );
        }

        $this->assertSame(1, $sms->calls);
        $this->assertSame(
            $firstUser->id,
            (int) NotificationLog::query()
                ->where(
                    'idempotency_key',
                    'notification-recipient-binding'
                )
                ->value('notifiable_id')
        );
    }

    private function activeUser(
        string $mobile = '09129990111'
    ): User {
        return User::factory()->create([
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    private function bindSenders(): object
    {
        $sms = new class implements SmsSender {
            public int $calls = 0;

            public function send(
                string $mobile,
                string $message
            ): array {
                $this->calls++;

                return [
                    'accepted' => true,
                    'provider_message_id' => 'sms-test-1',
                ];
            }
        };

        $push = new class implements PushSender {
            public function send(
                array $tokens,
                string $title,
                string $message,
                array $data = []
            ): array {
                return ['accepted' => true];
            }
        };

        $this->app->instance(SmsSender::class, $sms);
        $this->app->instance(PushSender::class, $push);

        return $sms;
    }
}
