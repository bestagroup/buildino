<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\SmsTemplateMessage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GhasedakSmsSender implements SmsSender
{
    public function send(string $mobile, string|SmsTemplateMessage $message): array
    {
        $endpoint = (string) config(
            'notifications.ghasedak.endpoint',
            'https://api.ghasedaksms.com/v2/send/verify'
        );
        $apiKey = (string) config('notifications.ghasedak.api_key');
        if (! $message instanceof SmsTemplateMessage) {
            throw new \InvalidArgumentException('Ghasedak requires an explicit SMS template message.');
        }
        $payload = app(SmsTemplateRegistry::class)->payload($message);

        if ($apiKey === '') {
            throw new RuntimeException('Ghasedak API key is not configured.');
        }

        if (
            app()->environment('production')
            && ! str_starts_with(strtolower($endpoint), 'https://')
        ) {
            throw new RuntimeException('Production Ghasedak endpoint must use HTTPS.');
        }

        $response = Http::acceptJson()
            ->asForm()
            ->withHeaders([
                'apikey' => $apiKey,
                'cache-control' => 'no-cache',
            ])
            ->timeout((int) config('notifications.ghasedak.timeout', 30))
            ->post($endpoint, [
                'type' => (int) config('notifications.ghasedak.type', 1),
                'receptor' => $mobile,
            ] + $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Ghasedak SMS provider HTTP failure: '.$response->status()
            );
        }

        $body = $response->json();
        if (! is_array($body) || (int) data_get($body, 'result.code', 0) !== 200) {
            // Do not leak the provider response (which may contain codes or phone numbers).
            throw new RuntimeException('Ghasedak rejected the SMS request or returned an invalid response.');
        }
        $messageId = data_get($body, config('notifications.ghasedak.message_id_path', 'items.0'))
            ?? data_get($body, 'items.0.messageid')
            ?? data_get($body, 'result.items.0.messageid');
        if (is_array($messageId)) {
            $messageId = $messageId['messageid'] ?? null;
        }

        return [
            'provider' => 'ghasedak',
            'accepted' => true,
            'status' => $response->status(),
            'provider_message_id' => $messageId,
            'response' => $body,
        ];
    }
}
