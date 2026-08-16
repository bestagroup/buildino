<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpPushSender implements PushSender
{
    public function send(
        array $tokens,
        string $title,
        string $message,
        array $data = []
    ): array {
        $endpoint = (string) config('notifications.http_push.endpoint');

        if ($endpoint === '') {
            throw new RuntimeException('HTTP Push endpoint is not configured.');
        }

        if (
            app()->environment('production')
            && ! str_starts_with(strtolower($endpoint), 'https://')
        ) {
            throw new RuntimeException('Production Push endpoint must use HTTPS.');
        }

        if ($tokens === []) {
            return [
                'provider' => 'http',
                'accepted' => false,
                'tokens_count' => 0,
            ];
        }

        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('notifications.http_push.timeout', 15));

        $token = (string) config('notifications.http_push.token');
        $header = (string) config('notifications.http_push.token_header', 'Authorization');
        $prefix = (string) config('notifications.http_push.token_prefix', 'Bearer ');

        if ($token !== '') {
            $request = $request->withHeaders([
                $header => $prefix.$token,
            ]);
        }

        $response = $request->post($endpoint, [
            'tokens' => array_values(array_unique($tokens)),
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Push provider HTTP failure: '.$response->status()
            );
        }

        $body =
            is_array(
                $response->json()
            )
                ? $response->json()
                : [
                    'body' =>
                        mb_substr(
                            $response->body(),
                            0,
                            2000
                        ),
                ];

        return [
            'provider' => 'http',
            'accepted' => true,
            'tokens_count' => count(array_unique($tokens)),
            'status' => $response->status(),
            'provider_message_id' =>
                data_get(
                    $body,
                    config(
                        'notifications.http_push.message_id_path',
                        'id'
                    )
                ),
            'response' => $body,
        ];
    }
}
