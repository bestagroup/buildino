<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payment\VerifyPayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentOperationController extends Controller
{
    public function verify(Payment $payment, VerifyPayment $action): JsonResponse
    {
        $this->authorize('verify', $payment);
        return response()->json(['data' => $action->execute($payment, request()->user())]);
    }
}
