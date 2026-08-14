<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateGatewayPaymentRequest;
use App\Http\Resources\V1\PaymentGatewayEventResource;
use App\Http\Resources\V1\PaymentGatewayTransactionResource;
use App\Models\Payment;
use App\Models\PaymentGatewayEvent;
use App\Services\Payments\PaymentGatewayCallbackService;
use App\Services\Payments\PaymentGatewayInitiationService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentGatewayController extends Controller
{
    public function initiate(
        InitiateGatewayPaymentRequest $request,
        Payment $payment,
        PaymentGatewayInitiationService $service,
        PermissionChecker $permissions
    ): PaymentGatewayTransactionResource {
        $payment->loadMissing('building');

        $user = $request->user();

        $allowed =
            (int) $payment->payer_user_id
                === (int) $user->getKey()
            || (
                $payment->building
                && $permissions->allows(
                    $user,
                    'payments.create',
                    $payment->building
                )
            );

        abort_unless(
            $allowed,
            403
        );

        return new PaymentGatewayTransactionResource(
            $service->initiate(
                $payment,
                $request->validated(
                    'gateway'
                ),
                $request->validated(
                    'idempotency_key'
                ),
                $user
            )
        );
    }

    public function callback(
        Request $request,
        string $gateway,
        PaymentGatewayCallbackService $service
    ): JsonResponse {
        $event = $service->callback(
            $gateway,
            $request->all(),
            $request->ip(),
            $request->userAgent()
        );

        $event->loadMissing(
            'paymentTransaction.payment'
        );

        $payment =
            $event
                ->paymentTransaction
                ?->payment;

        return response()->json([
            'data' => [
                'event_id' =>
                    $event->uuid,

                'gateway' =>
                    $gateway,

                'status' =>
                    is_object($event->status)
                        ? $event->status->value
                        : $event->status,

                'payment' =>
                    $payment
                        ? [
                            'id' =>
                                $payment->id,
                            'uuid' =>
                                $payment->uuid,
                            'status' =>
                                is_object(
                                    $payment->status
                                )
                                    ? $payment
                                        ->status
                                        ->value
                                    : $payment
                                        ->status,
                            'verified_at' =>
                                $payment
                                    ->verified_at
                                    ?->toISOString(),
                        ]
                        : null,
            ],
        ]);
    }

    public function webhook(
        Request $request,
        string $gateway,
        PaymentGatewayCallbackService $service
    ): JsonResponse {
        $rawBody =
            $request->getContent();

        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        $event = $service->webhook(
            $gateway,
            $rawBody,
            $payload,
            $request->headers->all(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'ok' => true,
            'event_id' => $event->uuid,
            'status' =>
                is_object($event->status)
                    ? $event->status->value
                    : $event->status,
        ]);
    }

    public function events(
        Request $request,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'payments.gateway-events.view',
                null
            ),
            403
        );

        $query = PaymentGatewayEvent::query()
            ->latest('id');

        if ($request->filled('gateway')) {
            $query->where(
                'gateway',
                $request->string(
                    'gateway'
                )->toString()
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string(
                    'status'
                )->toString()
            );
        }

        return PaymentGatewayEventResource::collection(
            $query->paginate(
                min(
                    100,
                    max(
                        1,
                        $request->integer(
                            'per_page',
                            20
                        )
                    )
                )
            )
        );
    }
}
