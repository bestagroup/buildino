<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\V1\PaymentResource;
use App\Models\Building;
use App\Models\Payment;
use App\Models\UnitInvoice;
use App\Services\InvoiceAccessService;
use App\Services\PaymentService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'payments.view',
                $building
            ),
            403
        );

        return PaymentResource::collection(
            $building->payments()
                ->with('paymentAllocations')
                ->latest('id')
                ->paginate(
                    min(
                        max($request->integer('per_page', 20), 1),
                        100
                    )
                )
                ->withQueryString()
        );
    }

    public function store(
        StorePaymentRequest $request,
        UnitInvoice $unitInvoice,
        InvoiceAccessService $access,
        PaymentService $service
    ) {
        abort_unless(
            $access->canPay(
                $request->user(),
                $unitInvoice
            ),
            403
        );

        $payment = $service->createForInvoice(
            $unitInvoice,
            $request->user(),
            $request->validated()
        );

        $payment->load('paymentAllocations');

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        Payment $payment,
        PermissionChecker $permissions
    ): PaymentResource {
        $payment->loadMissing('building');

        $allowed = (int) $payment->payer_user_id
                === (int) $request->user()->getKey()
            || (
                $payment->building
                && $permissions->allows(
                    $request->user(),
                    'payments.view',
                    $payment->building
                )
            );

        abort_unless($allowed, 403);

        $payment->load('paymentAllocations');

        return new PaymentResource($payment);
    }
}
