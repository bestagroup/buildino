<?php

namespace App\Actions\Payment;

use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;

class VerifyPayment
{
    public function __construct(private readonly PaymentService $service) {}

    public function execute(Payment $payment, User $user): Payment
    {
        return $this->service->verify($payment, $user);
    }
}
