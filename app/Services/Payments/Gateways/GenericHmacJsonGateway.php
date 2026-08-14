<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use App\Data\Payments\GatewayInitiationResult;
use App\Data\Payments\GatewayVerificationResult;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class GenericHmacJsonGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $name,
        private readonly array $config
    ) {
    }

    public function key(): string
    {
        return $this->name;
    }

    public function initiate(
        Payment $payment,
        PaymentTransaction $transaction,
        string $callbackUrl
    ): GatewayInitiationResult {
        $payload = [
            'merchant_id' =>
                $this->requiredConfig(
                    'merchant_id'
                ),

            'order_id' => $payment->uuid,
            'payment_number' =>
                $payment->payment_number,

            'amount' => (int) $payment->amount,
            'currency' =>
                strtoupper($payment->currency),

            'callback_url' => $callbackUrl,

            'idempotency_key' =>
                $transaction->idempotency_key,
        ];

        $response = $this->postSigned(
            $this->requiredConfig(
                'request_url'
            ),
            $payload
        );

        $body = $this->json($response);

        $authority = data_get(
            $body,
            $this->config[
                'authority_response_path'
            ] ?? 'data.authority'
        );

        if (! is_scalar($authority) || trim(
            (string) $authority
        ) === '') {
            throw ValidationException::withMessages([
                'gateway' =>
                    'Gateway initiation response did not contain an authority/token.',
            ]);
        }

        $authority = (string) $authority;

        $redirect = data_get(
            $body,
            $this->config[
                'redirect_url_response_path'
            ] ?? 'data.redirect_url'
        );

        if (! is_string($redirect) || $redirect === '') {
            $template = $this->config[
                'redirect_url_template'
            ] ?? null;

            if (! is_string($template) || $template === '') {
                throw ValidationException::withMessages([
                    'gateway' =>
                        'Gateway initiation response did not contain a redirect URL.',
                ]);
            }

            $redirect = str_replace(
                '{authority}',
                rawurlencode($authority),
                $template
            );
        }

        return new GatewayInitiationResult(
            authority: $authority,
            redirectUrl: $redirect,
            gatewayTransactionId:
                $this->scalarOrNull(
                    data_get(
                        $body,
                        $this->config[
                            'gateway_transaction_id_path'
                        ] ?? 'data.transaction_id'
                    )
                ),
            raw: $body
        );
    }

    public function verify(
        Payment $payment,
        PaymentTransaction $transaction
    ): GatewayVerificationResult {
        if (! $transaction->authority) {
            throw ValidationException::withMessages([
                'authority' =>
                    'Payment transaction does not have a gateway authority.',
            ]);
        }

        $payload = [
            'merchant_id' =>
                $this->requiredConfig(
                    'merchant_id'
                ),

            'authority' =>
                $transaction->authority,

            'order_id' =>
                $payment->uuid,

            'payment_number' =>
                $payment->payment_number,

            'amount' =>
                (int) $payment->amount,

            'currency' =>
                strtoupper($payment->currency),
        ];

        $response = $this->postSigned(
            $this->requiredConfig(
                'verify_url'
            ),
            $payload
        );

        $body = $this->json($response);

        $successValue = data_get(
            $body,
            $this->config[
                'verify_success_path'
            ] ?? 'data.success'
        );

        $successValues =
            $this->config[
                'verify_success_values'
            ] ?? [true, 1, '1'];

        $successful = in_array(
            $successValue,
            $successValues,
            true
        );

        return new GatewayVerificationResult(
            successful: $successful,
            amount: $this->intOrNull(
                data_get(
                    $body,
                    $this->config[
                        'verify_amount_path'
                    ] ?? 'data.amount'
                )
            ),
            currency: $this->scalarOrNull(
                data_get(
                    $body,
                    $this->config[
                        'verify_currency_path'
                    ] ?? 'data.currency'
                )
            ),
            gatewayTransactionId:
                $this->scalarOrNull(
                    data_get(
                        $body,
                        $this->config[
                            'verify_gateway_transaction_id_path'
                        ] ?? 'data.transaction_id'
                    )
                ),
            trackingCode:
                $this->scalarOrNull(
                    data_get(
                        $body,
                        $this->config[
                            'verify_tracking_code_path'
                        ] ?? 'data.tracking_code'
                    )
                ),
            referenceNumber:
                $this->scalarOrNull(
                    data_get(
                        $body,
                        $this->config[
                            'verify_reference_number_path'
                        ] ?? 'data.reference_number'
                    )
                ),
            merchantReference:
                $this->scalarOrNull(
                    data_get(
                        $body,
                        $this->config[
                            'verify_merchant_reference_path'
                        ] ?? 'data.order_id'
                    )
                ),
            errorCode:
                $this->scalarOrNull(
                    data_get(
                        $body,
                        $this->config[
                            'verify_error_code_path'
                        ] ?? 'error.code'
                    )
                ),
            errorMessage:
                $this->scalarOrNull(
                    data_get(
                        $body,
                        $this->config[
                            'verify_error_message_path'
                        ] ?? 'error.message'
                    )
                ),
            raw: $body
        );
    }

    public function extractAuthority(
        array $payload
    ): ?string {
        foreach (
            $this->config[
                'callback_authority_fields'
            ] ?? ['authority']
            as $field
        ) {
            $value = data_get(
                $payload,
                $field
            );

            if (
                is_scalar($value)
                && trim((string) $value) !== ''
            ) {
                return (string) $value;
            }
        }

        return null;
    }

    public function webhookEventKey(
        array $payload,
        array $headers
    ): ?string {
        $header = $this->header(
            $headers,
            $this->config[
                'webhook_event_id_header'
            ] ?? 'X-Event-Id'
        );

        if ($header !== null && $header !== '') {
            return $header;
        }

        foreach (
            $this->config[
                'webhook_event_id_fields'
            ] ?? ['event_id']
            as $field
        ) {
            $value = data_get(
                $payload,
                $field
            );

            if (
                is_scalar($value)
                && trim((string) $value) !== ''
            ) {
                return (string) $value;
            }
        }

        return null;
    }

    public function verifyWebhookSignature(
        string $rawBody,
        array $headers
    ): bool {
        $secret = $this->config[
            'webhook_secret'
        ] ?? null;

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $signature = $this->header(
            $headers,
            $this->config[
                'webhook_signature_header'
            ] ?? 'X-Signature'
        );

        $timestamp = $this->header(
            $headers,
            $this->config[
                'webhook_timestamp_header'
            ] ?? 'X-Timestamp'
        );

        if (
            $signature === null
            || $timestamp === null
            || ! ctype_digit($timestamp)
        ) {
            return false;
        }

        $maxSkew = (int) config(
            'payment_gateways.webhook_max_skew_seconds',
            300
        );

        if (
            abs(time() - (int) $timestamp)
            > $maxSkew
        ) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $timestamp.'.'.$rawBody,
            $secret
        );

        if (str_starts_with(
            strtolower($signature),
            'sha256='
        )) {
            $signature = substr(
                $signature,
                7
            );
        }

        return hash_equals(
            $expected,
            $signature
        );
    }

    private function postSigned(
        string $url,
        array $payload
    ): Response {
        $this->assertSecureUrl($url);

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );

        $headers = [];

        if (
            $this->config[
                'request_signature_enabled'
            ] ?? true
        ) {
            $secret = $this->requiredConfig(
                'secret'
            );

            $timestamp = (string) time();

            $headers[
                $this->config[
                    'request_timestamp_header'
                ] ?? 'X-Timestamp'
            ] = $timestamp;

            $headers[
                $this->config[
                    'request_signature_header'
                ] ?? 'X-Signature'
            ] = hash_hmac(
                'sha256',
                $timestamp.'.'.$json,
                $secret
            );
        }

        $response = Http::acceptJson()
            ->timeout(
                (int) (
                    $this->config[
                        'timeout_seconds'
                    ] ?? 15
                )
            )
            ->withHeaders($headers)
            ->withBody(
                $json,
                'application/json'
            )
            ->post($url);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'gateway' => sprintf(
                    'Gateway HTTP request failed with status %d.',
                    $response->status()
                ),
            ]);
        }

        return $response;
    }

    private function json(
        Response $response
    ): array {
        $data = $response->json();

        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'gateway' =>
                    'Gateway returned an invalid JSON response.',
            ]);
        }

        return $data;
    }

    private function requiredConfig(
        string $key
    ): string {
        $value = $this->config[$key]
            ?? null;

        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages([
                'gateway' =>
                    "Payment gateway configuration [{$key}] is missing.",
            ]);
        }

        return $value;
    }

    private function assertSecureUrl(
        string $url
    ): void {
        $scheme = parse_url(
            $url,
            PHP_URL_SCHEME
        );

        if (
            app()->environment('production')
            && strtolower((string) $scheme)
                !== 'https'
        ) {
            throw ValidationException::withMessages([
                'gateway' =>
                    'Production payment gateway endpoints must use HTTPS.',
            ]);
        }
    }

    private function header(
        array $headers,
        string $name
    ): ?string {
        foreach ($headers as $key => $value) {
            if (strcasecmp(
                (string) $key,
                $name
            ) !== 0) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0]
                    ?? null;
            }

            return is_scalar($value)
                ? (string) $value
                : null;
        }

        return null;
    }

    private function scalarOrNull(
        mixed $value
    ): ?string {
        return is_scalar($value)
            ? (string) $value
            : null;
    }

    private function intOrNull(
        mixed $value
    ): ?int {
        return is_numeric($value)
            ? (int) $value
            : null;
    }
}
