<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GhasedakSmsSender implements SmsSender
{
    public function send(string $mobile, string $message): array
    {
        $endpoint = (string) config(
            'notifications.ghasedak.endpoint',
            'https://api.ghasedaksms.com/v2/send/verify'
        );
        $apiKey = (string) config('notifications.ghasedak.api_key');
        $template = (string) config('notifications.ghasedak.template');

        if ($apiKey === '') {
            throw new RuntimeException('Ghasedak API key is not configured.');
        }

        if ($template === '') {
            throw new RuntimeException('Ghasedak SMS template is not configured.');
        }

        if (
            app()->environment('production')
            && ! str_starts_with(strtolower($endpoint), 'https://')
        ) {
            throw new RuntimeException('Production Ghasedak endpoint must use HTTPS.');
        }

        $param1 = $this->resolveTemplateParam($message);

        $response = Http::acceptJson()
            ->asForm()
            ->withHeaders([
                'apikey' => $apiKey,
                'cache-control' => 'no-cache',
            ])
            ->timeout((int) config('notifications.ghasedak.timeout', 30))
            ->post($endpoint, [
                'type' => (int) config('notifications.ghasedak.type', 1),
                'param1' => $param1,
                'receptor' => $mobile,
                'template' => $template,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Ghasedak SMS provider HTTP failure: '.$response->status()
            );
        }

        $body = is_array($response->json())
            ? $response->json()
            : [
                'body' => mb_substr($response->body(), 0, 2000),
            ];

        return [
            'provider' => 'ghasedak',
            'accepted' => true,
            'status' => $response->status(),
            'provider_message_id' => data_get(
                $body,
                config(
                    'notifications.ghasedak.message_id_path',
                    'result.items.0.messageid'
                )
            ),
            'response' => $body,
        ];
    }

    private function resolveTemplateParam(string $message): string
    {
        if (
            preg_match('/(?<!\\d)(\\d{4,10})(?!\\d)/u', $message, $matches)
            && isset($matches[1])
        ) {
            return $matches[1];
        }

        return $message;
    }
}
