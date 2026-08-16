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

class UserNotificationFcmCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_push_token_is_detached_without_being_written_to_notification_log(): void
    {
        $user =
            User::factory()
                ->create([
                    'mobile' =>
                        '09124445566',

                    'is_active' =>
                        true,

                    'is_blocked' =>
                        false,
                ]);

        UserDevice::query()
            ->create([
                'user_id' =>
                    $user->getKey(),

                'device_id' =>
                    'fcm-cleanup-device',

                'platform' =>
                    'android',

                'device_name' =>
                    'Pixel',

                'push_token' =>
                    'stale-secret-token',

                'last_used_at' =>
                    now(),
            ]);

        $push =
            new class implements PushSender {
                public function send(
                    array $tokens,
                    string $title,
                    string $message,
                    array $data = []
                ): array {
                    return [
                        'provider' =>
                            'fcm_v1',

                        'accepted' =>
                            false,

                        'tokens_count' =>
                            1,

                        'sent_count' =>
                            0,

                        'failed_count' =>
                            1,

                        'invalid_tokens' => [
                            'stale-secret-token',
                        ],

                        'failed' => [
                            [
                                'token_hash' =>
                                    hash(
                                        'sha256',
                                        'stale-secret-token'
                                    ),

                                'fcm_error_code' =>
                                    'UNREGISTERED',
                            ],
                        ],
                    ];
                }
            };

        $sms =
            new class implements SmsSender {
                public function send(
                    string $mobile,
                    string $message
                ): array {
                    return [
                        'accepted' =>
                            true,
                    ];
                }
            };

        $this->app
            ->instance(
                PushSender::class,
                $push
            );

        $this->app
            ->instance(
                SmsSender::class,
                $sms
            );

        try {
            app(
                UserNotificationService::class
            )->send(
                $user,
                new NotificationMessage(
                    'mobile.test',
                    'Buildino',
                    'Push test'
                ),
                'push',
                'fcm-cleanup-test'
            );
        } catch (\RuntimeException) {
            // Expected: every target device was rejected after cleanup.
        }

        $this->assertNull(
            UserDevice::query()
                ->where(
                    'device_id',
                    'fcm-cleanup-device'
                )
                ->value(
                    'push_token'
                )
        );

        $log =
            NotificationLog::query()
                ->where(
                    'idempotency_key',
                    'fcm-cleanup-test'
                )
                ->firstOrFail();

        $serialized =
            json_encode(
                $log->response,
                JSON_UNESCAPED_SLASHES
            );

        $this->assertStringNotContainsString(
            'stale-secret-token',
            (string) $serialized
        );
    }
}
