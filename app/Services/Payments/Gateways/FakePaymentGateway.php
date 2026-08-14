<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\Payments\PaymentGateway;
use App\Data\Payments\GatewayInitiationResult;
use App\Data\Payments\GatewayVerificationResult;
use App\Models\Payment;
use App\Models\PaymentTransaction;

final class FakePaymentGateway implements PaymentGateway
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
        $authority =
            'FAKE-'.$payment->uuid;

        return new GatewayInitiationResult(
            authority: $authority,
            redirectUrl:
                $callbackUrl
                .'?authority='
                .rawurlencode($authority),
            gatewayTransactionId:
                'FAKE-TX-'.$transaction->id,
            raw: [
                'success' => true,
                'authority' => $authority,
            ]
        );
    }

    public function verify(
        Payment $payment,
        PaymentTransaction $transaction
    ): GatewayVerificationResult {
        $override =
            $this->config[
                'verification'
            ] ?? [];

        return new GatewayVerificationResult(
            successful:
                (bool) (
                    $override['successful']
                    ?? true
                ),
            amount:
                array_key_exists(
                    'amount',
                    $override
                )
                    ? (
                        $override['amount'] === null
                            ? null
                            : (int) $override['amount']
                    )
                    : (int) $payment->amount,
            currency:
                $override['currency']
                    ?? $payment->currency,
            gatewayTransactionId:
                $override[
                    'gateway_transaction_id'
                ]
                    ?? (
                        $transaction
                            ->gateway_transaction_id
                        ?: 'FAKE-VERIFIED-'
                            .$transaction->id
                    ),
            trackingCode:
                $override['tracking_code']
                    ?? 'FAKE-TRACK-'
                        .$transaction->id,
            referenceNumber:
                $override['reference_number']
                    ?? 'FAKE-REF-'
                        .$transaction->id,
            merchantReference:
                $override['merchant_reference']
                    ?? $payment->uuid,
            errorCode:
                $override['error_code']
                    ?? null,
            errorMessage:
                $override['error_message']
                    ?? null,
            raw: [
                'fake' => true,
                'authority' =>
                    $transaction->authority,
            ]
        );
    }

    public function extractAuthority(
        array $payload
    ): ?string {
        foreach (
            ['authority', 'Authority', 'token']
            as $field
        ) {
            $value = $payload[$field]
                ?? null;

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
        foreach ($headers as $key => $value) {
            if (
                strcasecmp(
                    (string) $key,
                    'X-Event-Id'
                ) === 0
            ) {
                if (is_array($value)) {
                    $value = $value[0]
                        ?? null;
                }

                return is_scalar($value)
                    ? (string) $value
                    : null;
            }
        }

        $value = $payload['event_id']
            ?? null;

        return is_scalar($value)
            ? (string) $value
            : null;
    }

    public function verifyWebhookSignature(
        string $rawBody,
        array $headers
    ): bool {
        $secret = $this->config[
            'webhook_secret'
        ] ?? '';

        $signature = null;
        $timestamp = null;

        foreach ($headers as $key => $value) {
            if (is_array($value)) {
                $value = $value[0]
                    ?? null;
            }

            if (
                strcasecmp(
                    (string) $key,
                    'X-Signature'
                ) === 0
            ) {
                $signature = is_scalar($value)
                    ? (string) $value
                    : null;
            }

            if (
                strcasecmp(
                    (string) $key,
                    'X-Timestamp'
                ) === 0
            ) {
                $timestamp = is_scalar($value)
                    ? (string) $value
                    : null;
            }
        }

        if (
            ! $signature
            || ! $timestamp
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

        return hash_equals(
            hash_hmac(
                'sha256',
                $timestamp.'.'.$rawBody,
                $secret
            ),
            $signature
        );
    }
}
