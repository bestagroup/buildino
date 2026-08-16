<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FcmV1PushSender implements PushSender
{
    public function __construct(
        private readonly FcmAccessTokenService $accessTokens
    ) {
    }

    public function send(
        array $tokens,
        string $title,
        string $message,
        array $data = []
    ): array {
        $tokens =
            collect(
                $tokens
            )
                ->filter(
                    fn ($token): bool =>
                        is_string($token)
                        && trim($token) !== ''
                )
                ->map(
                    fn (string $token): string =>
                        trim($token)
                )
                ->unique()
                ->values()
                ->all();

        if ($tokens === []) {
            return [
                'provider' =>
                    'fcm_v1',

                'accepted' =>
                    false,

                'tokens_count' =>
                    0,

                'sent_count' =>
                    0,

                'failed_count' =>
                    0,

                'invalid_tokens' =>
                    [],
            ];
        }

        $projectId =
            trim(
                (string) config(
                    'notifications.fcm.project_id',
                    ''
                )
            );

        if ($projectId === '') {
            throw new RuntimeException(
                'FCM project_id is not configured.'
            );
        }

        $endpoint =
            'https://fcm.googleapis.com/v1/projects/'
            . rawurlencode(
                $projectId
            )
            . '/messages:send';

        $accessToken =
            $this->accessTokens
                ->token();

        $sent = [];
        $failed = [];
        $invalidTokens = [];

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' =>
                        $token,

                    'notification' => [
                        'title' =>
                            $title,

                        'body' =>
                            $message,
                    ],

                    'data' =>
                        $this->stringifyData(
                            $data
                        ),

                    'android' => [
                        'priority' =>
                            (string) config(
                                'notifications.fcm.android_priority',
                                'high'
                            ),
                    ],

                    'apns' => [
                        'headers' => [
                            'apns-priority' =>
                                (string) config(
                                    'notifications.fcm.apns_priority',
                                    '10'
                                ),
                        ],
                    ],
                ],
            ];

            $response =
                Http::acceptJson()
                    ->asJson()
                    ->withToken(
                        $accessToken
                    )
                    ->timeout(
                        (int) config(
                            'notifications.fcm.timeout',
                            15
                        )
                    )
                    ->post(
                        $endpoint,
                        $payload
                    );

            if ($response->successful()) {
                $sent[] = [
                    'token_hash' =>
                        hash(
                            'sha256',
                            $token
                        ),

                    'name' =>
                        $response->json(
                            'name'
                        ),
                ];

                continue;
            }

            $errorStatus =
                (string) $response
                    ->json(
                        'error.status',
                        ''
                    );

            $fcmErrorCode =
                $this->fcmErrorCode(
                    $response->json()
                    ?: []
                );

            $errorMessage =
                (string) $response
                    ->json(
                        'error.message',
                        ''
                    );

            if (
                $errorStatus === 'UNREGISTERED'
                || $fcmErrorCode === 'UNREGISTERED'
                || (
                    $fcmErrorCode === 'INVALID_ARGUMENT'
                    && str_contains(
                        strtolower(
                            $errorMessage
                        ),
                        'registration token'
                    )
                )
            ) {
                $invalidTokens[] =
                    $token;
            }

            $failed[] = [
                'token_hash' =>
                    hash(
                        'sha256',
                        $token
                    ),

                'http_status' =>
                    $response->status(),

                'status' =>
                    $errorStatus
                    ?: null,

                'fcm_error_code' =>
                    $fcmErrorCode,

                'message' =>
                    mb_substr(
                        (string) $response
                            ->json(
                                'error.message',
                                'FCM send failed.'
                            ),
                        0,
                        1000
                    ),
            ];
        }

        return [
            'provider' =>
                'fcm_v1',

            'accepted' =>
                $sent !== [],

            'tokens_count' =>
                count(
                    $tokens
                ),

            'sent_count' =>
                count(
                    $sent
                ),

            'failed_count' =>
                count(
                    $failed
                ),

            'provider_message_id' =>
                $sent[0]['name']
                ?? null,

            'invalid_tokens' =>
                array_values(
                    array_unique(
                        $invalidTokens
                    )
                ),

            'sent' =>
                $sent,

            'failed' =>
                $failed,
        ];
    }

    private function stringifyData(
        array $data
    ): array {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (
                is_scalar($value)
                || $value === null
            ) {
                $normalized[
                    (string) $key
                ] =
                    $value === null
                        ? ''
                        : (string) $value;

                continue;
            }

            $normalized[
                (string) $key
            ] =
                json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );
        }

        return $normalized;
    }

    private function fcmErrorCode(
        array $response
    ): ?string {
        foreach (
            data_get(
                $response,
                'error.details',
                []
            )
            as $detail
        ) {
            if (
                ! is_array($detail)
            ) {
                continue;
            }

            $code =
                $detail[
                    'errorCode'
                ]
                ?? null;

            if (
                is_string($code)
                && $code !== ''
            ) {
                return $code;
            }
        }

        return null;
    }
}
