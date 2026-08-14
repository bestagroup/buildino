<?php

namespace Tests\Unit\Notifications;

use App\Services\Notifications\HttpPushSender;
use App\Services\Notifications\HttpSmsSender;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpNotificationSenderTest extends TestCase
{
    public function test_http_sms_sender_calls_configured_gateway(): void
    {
        config([
            'notifications.http_sms.endpoint' => 'https://notify.test/sms',
            'notifications.http_sms.token' => 'secret',
        ]);

        Http::fake([
            'https://notify.test/sms' => Http::response([
                'id' => 'sms-1',
            ], 200),
        ]);

        $response = (new HttpSmsSender())->send(
            '09120000000',
            'Hello'
        );

        $this->assertTrue($response['accepted']);

        Http::assertSent(
            fn ($request): bool =>
                $request->url() === 'https://notify.test/sms'
                && $request['to'] === '09120000000'
                && $request['message'] === 'Hello'
        );
    }

    public function test_http_push_sender_calls_configured_gateway(): void
    {
        config([
            'notifications.http_push.endpoint' => 'https://notify.test/push',
            'notifications.http_push.token' => 'secret',
        ]);

        Http::fake([
            'https://notify.test/push' => Http::response([
                'accepted' => 2,
            ], 200),
        ]);

        $response = (new HttpPushSender())->send(
            ['token-a', 'token-b'],
            'Title',
            'Message',
            ['id' => 1]
        );

        $this->assertTrue($response['accepted']);
        $this->assertSame(2, $response['tokens_count']);
    }
}
