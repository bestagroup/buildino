<?php

namespace App\Listeners;

use App\Events\PaymentVerified;
use App\Services\Loyalty\LoyaltyLedgerService;

final class AwardPaymentLoyaltyPoints
{
    public function __construct(
        private readonly LoyaltyLedgerService $loyalty
    ) {}

    public function handle(PaymentVerified $event): void
    {
        $this->loyalty->awardForPayment(
            $event->payment
        );
    }
}
