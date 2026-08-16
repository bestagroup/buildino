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

class InvalidPushTokenCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_push_token_is_detached_and_never_persisted_in_notification_log(): void
    {
        $user =
            User::factory()
                ->create([
                    'mobile' =>
                        '09120000191',

                    'mobile_verified_at' =>
                        now(),

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
                    'phone-invalid',

                'platform' =>
                    'android',

                'push_token' =>
                    'raw-invalid-fcm-token',

                'last_used_at' =>
                    now(),
            ]);

        UserDevice::query()
            ->create([
                'user_id' =>
                    $user->getKey(),

                'device_id' =>
                    'phone-valid',

                'platform' =>
                    'android',

                'push_token' =>
                    'raw-valid-fcm-token',

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
                            true,

                        'tokens_count' =>
                            2,

                        'sent_count' =>
                            1,

                        'failed_count' =>
                            1,

                        'invalid_tokens' => [
                            'raw-invalid-fcm-token',
                        ],

                        'sent' => [
                            [
                                'token_hash' =>
                                    hash(
                                        'sha256',
                                        'raw-valid-fcm-token'
                                    ),
                            ],
                        ],

                        'failed' => [
                            [
                                'token_hash' =>
                                    hash(
                                        'sha256',
                                        'raw-invalid-fcm-token'
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

        $this->app->instance(
            PushSender::class,
            $push
        );

        $this->app->instance(
            SmsSender::class,
            $sms
        );

        config([
            'notifications.push_provider' =>
                'fcm_v1',
        ]);

        $log =
            app(
                UserNotificationService::class
            )->send(
                $user,
                new NotificationMessage(
                    'test.push',
                    'Buildino',
                    'Push token lifecycle test'
                ),
                'push',
                'push-token-cleanup-test'
            );

        $this->assertNotNull(
            $log
        );

        $this->assertDatabaseHas(
            'user_devices',
            [
                'device_id' =>
                    'phone-invalid',

                'push_token' =>
                    null,
            ]
        );

        $this->assertDatabaseHas(
            'user_devices',
            [
                'device_id' =>
                    'phone-valid',

                'push_token' =>
                    'raw-valid-fcm-token',
            ]
        );

        $stored =
            NotificationLog::query()
                ->where(
                    'idempotency_key',
                    'push-token-cleanup-test'
                )
                ->firstOrFail();

        $json =
            json_encode(
                $stored->response,
                JSON_UNESCAPED_SLASHES
            );

        $this->assertStringNotContainsString(
            'raw-invalid-fcm-token',
            $json
        );

        $this->assertStringNotContainsString(
            'raw-valid-fcm-token',
            $json
        );
    }
}
