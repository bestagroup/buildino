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
use RuntimeException;
use Tests\TestCase;

class NotificationDiagnosticRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_provider_response_redacts_recipient_data(): void
    {
        $user = User::factory()->create([
            'mobile' => '09127778899',
            'email' => 'private@example.test',
            'mobile_verified_at' => now(),
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $sms = new class implements SmsSender {
            public function send(
                string $mobile,
                string $message
            ): array {
                return [
                    'accepted' => true,
                    'provider_message_id' => 'sms-redact-1',
                    'debug' => "delivered-to={$mobile}",
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

        app(UserNotificationService::class)->send(
            $user,
            new NotificationMessage(
                'security.sms',
                'Buildino',
                'Message'
            ),
            'sms',
            'notification-diagnostic-redaction'
        );

        $log = NotificationLog::query()
            ->where(
                'idempotency_key',
                'notification-diagnostic-redaction'
            )
            ->firstOrFail();

        $serialized = json_encode(
            $log->response,
            JSON_UNESCAPED_SLASHES
        );

        $this->assertStringNotContainsString(
            '09127778899',
            $serialized
        );

        $this->assertStringContainsString(
            '[REDACTED]',
            $serialized
        );
    }

    public function test_notification_model_does_not_serialize_delivery_diagnostics(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => now(),
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $log = NotificationLog::query()->create([
            'idempotency_key' => 'private-diagnostic-key',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notification_type' => 'security.visibility',
            'channel' => 'sms',
            'provider' => 'private-provider',
            'provider_message_id' => 'provider-message-123',
            'title' => 'Visible title',
            'message' => 'Visible message',
            'status' => NotificationStatus::Failed,
            'attempts' => 3,
            'failure_reason' => 'private failure diagnostic',
            'response' => [
                'trace_id' => 'private-trace',
            ],
        ]);

        $serialized = $log->toArray();

        $this->assertSame(
            'Visible title',
            $serialized['title']
        );
        $this->assertSame(
            'Visible message',
            $serialized['message']
        );

        foreach ([
            'idempotency_key',
            'notifiable_type',
            'notifiable_id',
            'provider',
            'provider_message_id',
            'attempts',
            'last_attempt_at',
            'failed_at',
            'failure_reason',
            'response',
        ] as $hidden) {
            $this->assertArrayNotHasKey(
                $hidden,
                $serialized
            );
        }
    }

    public function test_provider_exception_message_is_not_persisted(): void
    {
        $user = User::factory()->create([
            'mobile' => '09127770000',
            'mobile_verified_at' => now(),
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $sms = new class implements SmsSender {
            public function send(
                string $mobile,
                string $message
            ): array {
                throw new RuntimeException(
                    'secret-token=should-never-be-stored'
                );
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

        try {
            app(UserNotificationService::class)->send(
                $user,
                new NotificationMessage(
                    'security.sms.failure',
                    'Buildino',
                    'Message'
                ),
                'sms',
                'notification-failure-redaction'
            );

            $this->fail('Expected provider failure.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        $log = NotificationLog::query()
            ->where(
                'idempotency_key',
                'notification-failure-redaction'
            )
            ->firstOrFail();

        $this->assertSame(
            NotificationStatus::Failed,
            $log->status
        );

        $this->assertSame(
            'Notification delivery failed [RuntimeException].',
            $log->failure_reason
        );

        $this->assertStringNotContainsString(
            'secret-token',
            (string) $log->failure_reason
        );
    }
}
