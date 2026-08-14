<?php

namespace App\Services\Payments;

use App\Data\Payments\GatewayVerificationResult;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use Illuminate\Validation\ValidationException;

final class PaymentGatewayVerificationService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentService $payments
    ) {
    }

    public function verify(
        PaymentTransaction $transaction
    ): Payment {
        $transaction->loadMissing(
            'payment'
        );

        $payment = $transaction->payment;

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment' =>
                    'Gateway transaction is not attached to a payment.',
            ]);
        }

        if (! $transaction->gateway) {
            throw ValidationException::withMessages([
                'gateway' =>
                    'Gateway transaction does not specify a gateway.',
            ]);
        }

        $driver = $this->gateways->driver(
            $transaction->gateway
        );

        $result = $driver->verify(
            $payment,
            $transaction
        );

        $this->validateResult(
            $payment,
            $result
        );

        return $this->payments
            ->verifyFromGateway(
                $payment,
                $transaction,
                $result
            );
    }

    private function validateResult(
        Payment $payment,
        GatewayVerificationResult $result
    ): void {
        if (! $result->successful) {
            throw ValidationException::withMessages([
                'gateway' =>
                    $result->errorMessage
                    ?: 'Gateway did not verify this payment as successful.',
            ]);
        }

        if (
            $result->amount !== null
            && $result->amount
                !== (int) $payment->amount
        ) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Verified gateway amount does not match the payment amount.',
            ]);
        }

        if (
            $result->currency !== null
            && strtoupper(
                $result->currency
            ) !== strtoupper(
                $payment->currency
            )
        ) {
            throw ValidationException::withMessages([
                'currency' =>
                    'Verified gateway currency does not match the payment currency.',
            ]);
        }

        if (
            $result->merchantReference !== null
            && ! in_array(
                $result->merchantReference,
                [
                    $payment->uuid,
                    $payment->payment_number,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'reference' =>
                    'Verified gateway merchant reference does not match this payment.',
            ]);
        }
    }
}
