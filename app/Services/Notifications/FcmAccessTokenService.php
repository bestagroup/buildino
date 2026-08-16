<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FcmAccessTokenService
{
    private const SCOPE =
        'https://www.googleapis.com/auth/firebase.messaging';

    public function token(): string
    {
        $credentials =
            $this->credentials();

        $cacheKey =
            'buildino:fcm:access-token:'
            . hash(
                'sha256',
                (string) (
                    $credentials[
                        'client_email'
                    ]
                    ?? ''
                )
            );

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(50),
            fn (): string =>
                $this->requestToken(
                    $credentials
                )
        );
    }

    private function requestToken(
        array $credentials
    ): string {
        $clientEmail =
            trim(
                (string) (
                    $credentials[
                        'client_email'
                    ]
                    ?? ''
                )
            );

        $privateKey =
            (string) (
                $credentials[
                    'private_key'
                ]
                ?? ''
            );

        $tokenUri =
            trim(
                (string) (
                    $credentials[
                        'token_uri'
                    ]
                    ?? 'https://oauth2.googleapis.com/token'
                )
            );

        if (
            $clientEmail === ''
            || $privateKey === ''
            || $tokenUri === ''
        ) {
            throw new RuntimeException(
                'FCM service-account credentials are incomplete.'
            );
        }

        if (
            app()->environment(
                'production'
            )
            && ! str_starts_with(
                strtolower(
                    $tokenUri
                ),
                'https://'
            )
        ) {
            throw new RuntimeException(
                'FCM OAuth token endpoint must use HTTPS in production.'
            );
        }

        $now =
            time();

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $claims = [
            'iss' =>
                $clientEmail,

            'scope' =>
                self::SCOPE,

            'aud' =>
                $tokenUri,

            'iat' =>
                $now,

            'exp' =>
                $now + 3600,
        ];

        $unsigned =
            $this->base64Url(
                json_encode(
                    $header,
                    JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                )
            )
            . '.'
            . $this->base64Url(
                json_encode(
                    $claims,
                    JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                )
            );

        $key =
            openssl_pkey_get_private(
                $privateKey
            );

        if ($key === false) {
            throw new RuntimeException(
                'FCM private key is invalid.'
            );
        }

        $signed =
            openssl_sign(
                $unsigned,
                $signature,
                $key,
                OPENSSL_ALGO_SHA256
            );

        if (! $signed) {
            throw new RuntimeException(
                'Unable to sign FCM service-account JWT.'
            );
        }

        $assertion =
            $unsigned
            . '.'
            . $this->base64Url(
                $signature
            );

        $response =
            Http::asForm()
                ->acceptJson()
                ->timeout(
                    (int) config(
                        'notifications.fcm.timeout',
                        15
                    )
                )
                ->post(
                    $tokenUri,
                    [
                        'grant_type' =>
                            'urn:ietf:params:oauth:grant-type:jwt-bearer',

                        'assertion' =>
                            $assertion,
                    ]
                );

        if (! $response->successful()) {
            throw new RuntimeException(
                'FCM OAuth token request failed with HTTP '
                . $response->status()
                . '.'
            );
        }

        $token =
            trim(
                (string) $response
                    ->json(
                        'access_token'
                    )
            );

        if ($token === '') {
            throw new RuntimeException(
                'FCM OAuth response did not contain an access token.'
            );
        }

        $ttl =
            max(
                60,
                (int) $response
                    ->json(
                        'expires_in',
                        3600
                    )
                - 60
            );

        $cacheKey =
            'buildino:fcm:access-token:'
            . hash(
                'sha256',
                $clientEmail
            );

        Cache::put(
            $cacheKey,
            $token,
            now()->addSeconds(
                $ttl
            )
        );

        return $token;
    }

    private function credentials(): array
    {
        $base64 =
            trim(
                (string) config(
                    'notifications.fcm.credentials_json_base64',
                    ''
                )
            );

        if ($base64 !== '') {
            $decoded =
                base64_decode(
                    $base64,
                    true
                );

            if ($decoded === false) {
                throw new RuntimeException(
                    'FCM base64 credentials are invalid.'
                );
            }

            return $this->decodeJson(
                $decoded
            );
        }

        $path =
            trim(
                (string) config(
                    'notifications.fcm.credentials_path',
                    ''
                )
            );

        if ($path === '') {
            throw new RuntimeException(
                'FCM credentials are not configured.'
            );
        }

        if (! is_file($path)) {
            throw new RuntimeException(
                'FCM credentials file does not exist.'
            );
        }

        $contents =
            file_get_contents(
                $path
            );

        if ($contents === false) {
            throw new RuntimeException(
                'Unable to read FCM credentials file.'
            );
        }

        return $this->decodeJson(
            $contents
        );
    }

    private function decodeJson(
        string $json
    ): array {
        $decoded =
            json_decode(
                $json,
                true
            );

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'FCM credentials JSON is invalid.'
            );
        }

        return $decoded;
    }

    private function base64Url(
        string $value
    ): string {
        return rtrim(
            strtr(
                base64_encode(
                    $value
                ),
                '+/',
                '-_'
            ),
            '='
        );
    }
}
