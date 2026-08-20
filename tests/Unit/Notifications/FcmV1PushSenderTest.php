<?php

namespace Tests\Unit\Notifications;

use App\Services\Notifications\FcmV1PushSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmV1PushSenderTest extends TestCase
{
    public function test_fcm_http_v1_uses_oauth_and_reports_unregistered_tokens(): void
    {
        Cache::clear();

        $privateKey = (string) file_get_contents(
            base_path('tests/Fixtures/fcm_test_private_key.pem')
        );

        $this->assertStringContainsString(
            'BEGIN PRIVATE KEY',
            $privateKey
        );

        $credentials = [
            'type' =>
                'service_account',

            'project_id' =>
                'buildino-test',

            'private_key' =>
                $privateKey,

            'client_email' =>
                'firebase-adminsdk@buildino-test.iam.gserviceaccount.com',

            'token_uri' =>
                'https://oauth2.googleapis.com/token',
        ];

        config([
            'notifications.fcm.project_id' =>
                'buildino-test',

            'notifications.fcm.credentials_path' =>
                null,

            'notifications.fcm.credentials_json_base64' =>
                base64_encode(
                    json_encode(
                        $credentials,
                        JSON_THROW_ON_ERROR
                    )
                ),
        ]);

        Http::fake(
            function (
                Request $request
            ) {
                if (
                    $request->url()
                    === 'https://oauth2.googleapis.com/token'
                ) {
                    return Http::response([
                        'access_token' =>
                            'test-fcm-oauth-token',

                        'expires_in' =>
                            3600,

                        'token_type' =>
                            'Bearer',
                    ]);
                }

                $token =
                    data_get(
                        $request->data(),
                        'message.token'
                    );

                if ($token === 'valid-token') {
                    return Http::response([
                        'name' =>
                            'projects/buildino-test/messages/message-1',
                    ]);
                }

                return Http::response([
                    'error' => [
                        'code' =>
                            404,

                        'message' =>
                            'Requested entity was not found.',

                        'status' =>
                            'NOT_FOUND',

                        'details' => [
                            [
                                '@type' =>
                                    'type.googleapis.com/google.firebase.fcm.v1.FcmError',

                                'errorCode' =>
                                    'UNREGISTERED',
                            ],
                        ],
                    ],
                ], 404);
            }
        );

        $result =
            app(
                FcmV1PushSender::class
            )->send(
                [
                    'valid-token',
                    'invalid-token',
                ],
                'Buildino',
                'Test push',
                [
                    'route' =>
                        'support_ticket',

                    'id' =>
                        12,
                ]
            );

        $this->assertTrue(
            $result['accepted']
        );

        $this->assertSame(
            2,
            $result['tokens_count']
        );

        $this->assertSame(
            1,
            $result['sent_count']
        );

        $this->assertSame(
            1,
            $result['failed_count']
        );

        $this->assertSame(
            [
                'invalid-token',
            ],
            $result[
                'invalid_tokens'
            ]
        );

        Http::assertSent(
            fn (Request $request): bool =>
                $request->url()
                    === 'https://oauth2.googleapis.com/token'
        );

        Http::assertSent(
            fn (Request $request): bool =>
                str_contains(
                    $request->url(),
                    'fcm.googleapis.com/v1/projects/buildino-test/messages:send'
                )
                && $request->hasHeader(
                    'Authorization',
                    'Bearer test-fcm-oauth-token'
                )
        );
    }
}
