<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payment\VerifyPayment;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PaymentResource;
use App\Models\Payment;

class PaymentOperationController extends Controller
{
    public function verify(
        Payment $payment,
        VerifyPayment $action
    ): PaymentResource {
        $this->authorize('verify', $payment);

        $payment = $action->execute(
            $payment,
            request()->user()
        );

        $payment->load('paymentAllocations');

        return new PaymentResource($payment);
    }
}
