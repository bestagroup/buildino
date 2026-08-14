<?php

namespace App\Services\Payments;

final class GatewayPayloadSanitizer
{
    private const SENSITIVE = [
        'authorization',
        'password',
        'secret',
        'token_secret',
        'api_key',
        'apikey',
        'cvv',
        'cvv2',
        'pin',
        'pan',
        'card_number',
        'cardnumber',
    ];

    public function sanitize(
        array $payload
    ): array {
        return $this->walk(
            $payload
        );
    }

    private function walk(
        array $payload
    ): array {
        $clean = [];

        foreach ($payload as $key => $value) {
            $normalized = strtolower(
                str_replace(
                    ['-', ' '],
                    '_',
                    (string) $key
                )
            );

            if (in_array(
                $normalized,
                self::SENSITIVE,
                true
            )) {
                $clean[$key] =
                    '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $clean[$key] =
                    $this->walk($value);

                continue;
            }

            if (
                is_string($value)
                && strlen($value) > 5000
            ) {
                $clean[$key] =
                    substr(
                        $value,
                        0,
                        5000
                    );

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
