<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentGatewayInitiationService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly GatewayPayloadSanitizer $sanitizer
    ) {
    }

    public function initiate(
        Payment $payment,
        string $gatewayName,
        string $idempotencyKey,
        User $actor
    ): PaymentTransaction {
        if (! in_array(
            $payment->method,
            [
                PaymentMethod::Online,
                PaymentMethod::Qr,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'payment' =>
                    'Only online/QR payments can be initiated through an external gateway.',
            ]);
        }

        $driver = $this->gateways->driver(
            $gatewayName
        );

        $transaction = DB::transaction(
            function () use (
                $payment,
                $gatewayName,
                $idempotencyKey
            ): PaymentTransaction {
                $payment = Payment::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $payment->getKey()
                    );

                if ($payment->status === PaymentStatus::Paid) {
                    throw ValidationException::withMessages([
                        'payment' =>
                            'A paid payment cannot be initiated again.',
                    ]);
                }

                if (! in_array(
                    $payment->status,
                    [
                        PaymentStatus::Pending,
                        PaymentStatus::Processing,
                        PaymentStatus::Failed,
                    ],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'payment' =>
                            'Payment cannot be initiated in its current status.',
                    ]);
                }

                $existing = PaymentTransaction::query()
                    ->where(
                        'idempotency_key',
                        $idempotencyKey
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (
                        (int) $existing->payment_id
                            !== (int) $payment->getKey()
                    ) {
                        throw ValidationException::withMessages([
                            'idempotency_key' =>
                                'This idempotency key belongs to another payment.',
                        ]);
                    }

                    if (
                        $existing->gateway
                        && $existing->gateway
                            !== $gatewayName
                    ) {
                        throw ValidationException::withMessages([
                            'gateway' =>
                                'This payment transaction was created for another gateway.',
                        ]);
                    }

                    if (! $existing->gateway) {
                        $existing->update([
                            'gateway' =>
                                $gatewayName,
                        ]);
                    }

                    return $existing->refresh();
                }

                return PaymentTransaction::query()->create([
                    'payment_id' =>
                        $payment->getKey(),

                    'gateway' =>
                        $gatewayName,

                    'idempotency_key' =>
                        $idempotencyKey,

                    'request_payload' => [
                        'purpose' =>
                            'external_payment',
                    ],
                ])->refresh();
            },
            3
        );

        if (
            $transaction->authority
            && is_array(
                $transaction->response_payload
            )
            && ! empty(
                $transaction
                    ->response_payload[
                        'redirect_url'
                    ]
            )
        ) {
            return $transaction;
        }

        $callbackUrl = rtrim(
            (string) config(
                'payment_gateways.callback_base_url',
                config('app.url')
            ),
            '/'
        ).'/api/v1/payment-gateways/'
            .rawurlencode($driver->key())
            .'/callback';

        try {
            $result = $driver->initiate(
                $payment->fresh(),
                $transaction->fresh(),
                $callbackUrl
            );
        } catch (\Throwable $exception) {
            $transaction->update([
                'failed_at' => now(),
                'response_payload' => [
                    'stage' => 'initiate',
                    'error' => [
                        'type' =>
                            class_basename(
                                $exception
                            ),
                        'message' =>
                            mb_substr(
                                $exception
                                    ->getMessage(),
                                0,
                                1000
                            ),
                    ],
                ],
            ]);

            throw $exception;
        }

        return DB::transaction(function () use (
            $payment,
            $transaction,
            $gatewayName,
            $result,
            $actor
        ): PaymentTransaction {
            $transaction = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail(
                    $transaction->getKey()
                );

            /*
             * A concurrent retry may already have finished initiation.
             * Never overwrite the first valid authority.
             */
            if (
                $transaction->authority
                && is_array(
                    $transaction
                        ->response_payload
                )
                && ! empty(
                    $transaction
                        ->response_payload[
                            'redirect_url'
                        ]
                )
            ) {
                return $transaction;
            }

            $requestPayload = is_array(
                $transaction->request_payload
            )
                ? $transaction->request_payload
                : [];

            $transaction->update([
                'gateway' => $gatewayName,
                'authority' =>
                    $result->authority,
                'gateway_transaction_id' =>
                    $result
                        ->gatewayTransactionId,
                'request_payload' => [
                    ...$requestPayload,
                    'initiated_by' =>
                        $actor->getKey(),
                    'callback_url' =>
                        config(
                            'payment_gateways.callback_base_url',
                            config('app.url')
                        ),
                ],
                'response_payload' => [
                    'stage' => 'initiate',
                    'redirect_url' =>
                        $result->redirectUrl,
                    'gateway_response' =>
                        $this
                            ->sanitizer
                            ->sanitize(
                                $result->raw
                            ),
                ],
                'requested_at' => now(),
                'failed_at' => null,
            ]);

            Payment::query()
                ->whereKey(
                    $payment->getKey()
                )
                ->where(
                    'status',
                    '!=',
                    PaymentStatus::Paid->value
                )
                ->update([
                    'status' =>
                        PaymentStatus::Processing,
                ]);

            return $transaction->refresh();
        }, 3);
    }
}
