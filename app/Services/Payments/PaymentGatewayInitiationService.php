<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        $claimToken = (string) Str::uuid();

        $transaction = $this->claimInitiation(
            $payment,
            $gatewayName,
            $idempotencyKey,
            $claimToken
        );

        if ($this->isInitiated($transaction)) {
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
            $this->releaseFailedClaim(
                $transaction,
                $claimToken,
                $exception
            );

            throw $exception;
        }

        return DB::transaction(function () use (
            $payment,
            $transaction,
            $gatewayName,
            $result,
            $actor,
            $claimToken
        ): PaymentTransaction {
            $transaction = PaymentTransaction::query()
                ->lockForUpdate()
                ->findOrFail(
                    $transaction->getKey()
                );

            /*
             * A completed initiation is immutable. This can happen when a
             * retry observes the transaction after another request commits.
             */
            if ($this->isInitiated($transaction)) {
                return $transaction;
            }

            if (
                ! hash_equals(
                    (string) $transaction->initiation_token,
                    $claimToken
                )
            ) {
                throw new HttpException(
                    409,
                    'Gateway initiation was superseded by another retry.'
                );
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
                'initiation_token' => null,
                'initiating_at' => null,
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

    private function claimInitiation(
        Payment $payment,
        string $gatewayName,
        string $idempotencyKey,
        string $claimToken
    ): PaymentTransaction {
        try {
            return DB::transaction(
                fn (): PaymentTransaction =>
                    $this->claimInsideTransaction(
                        $payment,
                        $gatewayName,
                        $idempotencyKey,
                        $claimToken
                    ),
                3
            );
        } catch (QueryException $exception) {
            /*
             * Two first-time requests can race on UNIQUE(idempotency_key).
             * Only convert that race into an idempotent retry when the
             * winning row really exists; otherwise preserve the DB error.
             */
            $existing = PaymentTransaction::query()
                ->where(
                    'idempotency_key',
                    $idempotencyKey
                )
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return DB::transaction(
                fn (): PaymentTransaction =>
                    $this->claimInsideTransaction(
                        $payment,
                        $gatewayName,
                        $idempotencyKey,
                        $claimToken
                    ),
                3
            );
        }
    }

    private function claimInsideTransaction(
        Payment $payment,
        string $gatewayName,
        string $idempotencyKey,
        string $claimToken
    ): PaymentTransaction {
        $payment = Payment::query()
            ->lockForUpdate()
            ->findOrFail(
                $payment->getKey()
            );

        $this->assertPaymentCanBeInitiated(
            $payment
        );

        $transaction = PaymentTransaction::query()
            ->where(
                'idempotency_key',
                $idempotencyKey
            )
            ->lockForUpdate()
            ->first();

        if (! $transaction) {
            return PaymentTransaction::query()->create([
                'payment_id' =>
                    $payment->getKey(),
                'gateway' =>
                    $gatewayName,
                'idempotency_key' =>
                    $idempotencyKey,
                'initiation_token' =>
                    $claimToken,
                'initiating_at' => now(),
                'initiation_attempts' => 1,
                'request_payload' => [
                    'purpose' =>
                        'external_payment',
                ],
            ])->refresh();
        }

        $this->assertTransactionBinding(
            $transaction,
            $payment,
            $gatewayName
        );

        if ($this->isInitiated($transaction)) {
            return $transaction;
        }

        if ($this->hasActiveClaim($transaction)) {
            throw new HttpException(
                409,
                'Gateway initiation is already in progress.'
            );
        }

        $transaction->update([
            'gateway' =>
                $gatewayName,
            'initiation_token' =>
                $claimToken,
            'initiating_at' => now(),
            'initiation_attempts' =>
                (int) $transaction
                    ->initiation_attempts + 1,
            'failed_at' => null,
        ]);

        return $transaction->refresh();
    }

    private function assertPaymentCanBeInitiated(
        Payment $payment
    ): void {
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
    }

    private function assertTransactionBinding(
        PaymentTransaction $transaction,
        Payment $payment,
        string $gatewayName
    ): void {
        if (
            (int) $transaction->payment_id
                !== (int) $payment->getKey()
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' =>
                    'This idempotency key belongs to another payment.',
            ]);
        }

        if (
            $transaction->gateway
            && $transaction->gateway
                !== $gatewayName
        ) {
            throw ValidationException::withMessages([
                'gateway' =>
                    'This payment transaction was created for another gateway.',
            ]);
        }
    }

    private function hasActiveClaim(
        PaymentTransaction $transaction
    ): bool {
        if (
            ! $transaction->initiation_token
            || ! $transaction->initiating_at
        ) {
            return false;
        }

        $ttl = max(
            1,
            (int) config(
                'payment_gateways.initiation_claim_ttl_seconds',
                90
            )
        );

        return $transaction->initiating_at
            ->greaterThan(
                now()->subSeconds($ttl)
            );
    }

    private function isInitiated(
        PaymentTransaction $transaction
    ): bool {
        return (bool) $transaction->authority
            && is_array(
                $transaction->response_payload
            )
            && ! empty(
                $transaction
                    ->response_payload[
                        'redirect_url'
                    ]
            );
    }

    private function releaseFailedClaim(
        PaymentTransaction $transaction,
        string $claimToken,
        \Throwable $exception
    ): void {
        DB::transaction(function () use (
            $transaction,
            $claimToken,
            $exception
        ): void {
            $transaction = PaymentTransaction::query()
                ->lockForUpdate()
                ->find(
                    $transaction->getKey()
                );

            if (
                ! $transaction
                || ! hash_equals(
                    (string) $transaction->initiation_token,
                    $claimToken
                )
            ) {
                return;
            }

            $transaction->update([
                'failed_at' => now(),
                'initiation_token' => null,
                'initiating_at' => null,
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
        }, 3);
    }
}
