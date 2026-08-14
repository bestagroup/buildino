<?php

namespace App\Data\Payments;

final readonly class GatewayVerificationResult
{
    public function __construct(
        public bool $successful,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $gatewayTransactionId = null,
        public ?string $trackingCode = null,
        public ?string $referenceNumber = null,
        public ?string $merchantReference = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $raw = []
    ) {
    }
}
