<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\SmsTemplateMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $body = $response->json();
        $result = is_array($body) ? ($body['result'] ?? null) : null;
        // api.ghasedaksms.com returns result="success"/"error" at the root.
        // Retain the nested result.code contract for existing compatible endpoints.
        $flatResult = is_string($result);
        $rawCode = $flatResult
            ? ($body['status'] ?? $body['code'] ?? null)
            : (is_array($body) ? data_get($body, 'result.code') : null);
        $code = (is_int($rawCode) || is_string($rawCode))
            && preg_match('/^\d{1,4}$/D', (string) $rawCode)
                ? (string) $rawCode : ($rawCode === null ? 'missing' : 'invalid');

        $accepted = $flatResult
            ? ($result === 'success' && in_array($code, ['missing', '200'], true))
            : $code === '200';
        $knownFlatResult = $flatResult && in_array($result, ['success', 'error', 'failed', 'fail'], true);
        if (! $response->successful() || ! $accepted) {
            $reason = ! is_array($body) ? 'non_json_response'
                : (! $knownFlatResult && in_array($code, ['missing', 'invalid'], true) ? 'unexpected_response_schema' : 'provider_rejection');
            $providerText = is_array($body) ? ($flatResult ? ($body['message'] ?? null) : data_get($body, 'result.message')) : null;
            $fields = [];
            // Report only known field names, never provider prose or request values.
            // Upstream errors may echo API keys, mobile numbers and OTPs.
            if (is_string($providerText)) {
                foreach (['template', 'apikey', 'receptor', 'param1', 'param2', 'param3', 'balance'] as $field) {
                    if (preg_match('/\b'.preg_quote($field, '/').'\b/i', $providerText)) {
                        $fields[] = $field;
                    }
                }
            }
            $diagnostics = [
                'http_status' => $response->status(),
                'provider_code' => $code,
                'reason' => $reason,
                'mentioned_fields' => $fields,
                'purpose' => $message->purpose,
                'response_format' => $flatResult ? 'flat' : 'nested',
                'result' => $knownFlatResult ? $result : 'unrecognized',
            ];
            Log::warning('Ghasedak SMS request failed', $diagnostics);
            throw new RuntimeException(sprintf(
                'Ghasedak SMS failed [HTTP=%d; code=%s; reason=%s; fields=%s; format=%s; result=%s].',
                $response->status(), $code, $reason, implode(',', $fields) ?: 'none',
                $diagnostics['response_format'], $diagnostics['result']
            ));
        }
        $messageId = data_get($body, config('notifications.ghasedak.message_id_path', 'items.0'))
            ?? data_get($body, 'messageids.0')
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
