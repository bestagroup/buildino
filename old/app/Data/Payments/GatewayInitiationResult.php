<?php

namespace App\Data\Payments;

final readonly class GatewayInitiationResult
{
    public function __construct(
        public string $authority,
        public string $redirectUrl,
        public ?string $gatewayTransactionId = null,
        public array $raw = []
    ) {
    }
}
