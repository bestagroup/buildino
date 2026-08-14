<?php

namespace App\Contracts\Payments;

use App\Data\Payments\GatewayInitiationResult;
use App\Data\Payments\GatewayVerificationResult;
use App\Models\Payment;
use App\Models\PaymentTransaction;

interface PaymentGateway
{
    public function key(): string;

    public function initiate(
        Payment $payment,
        PaymentTransaction $transaction,
        string $callbackUrl
    ): GatewayInitiationResult;

    public function verify(
        Payment $payment,
        PaymentTransaction $transaction
    ): GatewayVerificationResult;

    public function extractAuthority(
        array $payload
    ): ?string;

    public function webhookEventKey(
        array $payload,
        array $headers
    ): ?string;

    public function verifyWebhookSignature(
        string $rawBody,
        array $headers
    ): bool;
}
