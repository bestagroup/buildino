<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpSmsSender implements SmsSender
{
    public function send(string $mobile, string $message): array
    {
        $endpoint = (string) config('notifications.http_sms.endpoint');

        if ($endpoint === '') {
            throw new RuntimeException('HTTP SMS endpoint is not configured.');
        }

        if (
            app()->environment('production')
            && ! str_starts_with(strtolower($endpoint), 'https://')
        ) {
            throw new RuntimeException('Production SMS endpoint must use HTTPS.');
        }

        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('notifications.http_sms.timeout', 15));

        $token = (string) config('notifications.http_sms.token');
        $header = (string) config('notifications.http_sms.token_header', 'Authorization');
        $prefix = (string) config('notifications.http_sms.token_prefix', 'Bearer ');

        if ($token !== '') {
            $request = $request->withHeaders([
                $header => $prefix.$token,
            ]);
        }

        $response = $request->post($endpoint, [
            'to' => $mobile,
            'message' => $message,
            'sender' => config('notifications.http_sms.sender'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'SMS provider HTTP failure: '.$response->status()
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
            'status' => $response->status(),
            'provider_message_id' =>
                data_get(
                    $body,
                    config(
                        'notifications.http_sms.message_id_path',
                        'id'
                    )
                ),
            'response' => $body,
        ];
    }
}
