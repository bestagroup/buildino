<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\PaymentGatewayEventStatus;
use App\Enums\PaymentGatewayEventType;
use App\Models\PaymentGatewayEvent;
use App\Models\PaymentTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PaymentGatewayCallbackService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentGatewayVerificationService $verification,
        private readonly GatewayPayloadSanitizer $sanitizer
    ) {
    }

    public function callback(
        string $gatewayName,
        array $payload,
        ?string $sourceIp = null,
        ?string $userAgent = null
    ): PaymentGatewayEvent {
        $gateway = $this->gateways->driver(
            $gatewayName
        );

        $authority = $gateway
            ->extractAuthority($payload);

        if (! $authority) {
            throw ValidationException::withMessages([
                'authority' =>
                    'Gateway callback does not contain an authority/token.',
            ]);
        }

        $payloadHash = $this->hashPayload(
            $payload
        );

        $event = $this->event(
            gateway: $gatewayName,
            eventKey:
                'callback:'
                .hash(
                    'sha256',
                    $authority.':'.$payloadHash
                ),
            type:
                PaymentGatewayEventType::Callback,
            authority: $authority,
            payload: $payload,
            payloadHash: $payloadHash,
            signatureValid: null,
            sourceIp: $sourceIp,
            userAgent: $userAgent
        );

        if (
            $event->status
            === PaymentGatewayEventStatus::Processed
        ) {
            return $event;
        }

        $transaction = PaymentTransaction::query()
            ->where(
                'gateway',
                $gatewayName
            )
            ->where(
                'authority',
                $authority
            )
            ->first();

        if (! $transaction) {
            $this->reject(
                $event,
                'No PaymentTransaction matches this gateway authority.'
            );

            throw ValidationException::withMessages([
                'authority' =>
                    'Unknown gateway authority.',
            ]);
        }

        return $this->process(
            $event,
            $transaction
        );
    }

    public function webhook(
        string $gatewayName,
        string $rawBody,
        array $payload,
        array $headers,
        ?string $sourceIp = null,
        ?string $userAgent = null
    ): PaymentGatewayEvent {
        $gateway = $this->gateways->driver(
            $gatewayName
        );

        $authority = $gateway
            ->extractAuthority($payload);

        $payloadHash = hash(
            'sha256',
            $rawBody
        );

        $providerEventKey =
            $gateway->webhookEventKey(
                $payload,
                $headers
            );

        $eventKey = $providerEventKey
            ? 'webhook:'.$providerEventKey
            : 'webhook:'.$payloadHash;

        $signatureValid =
            $gateway
                ->verifyWebhookSignature(
                    $rawBody,
                    $headers
                );

        $event = $this->event(
            gateway: $gatewayName,
            eventKey: $eventKey,
            type:
                PaymentGatewayEventType::Webhook,
            authority: $authority,
            payload: $payload,
            payloadHash: $payloadHash,
            signatureValid:
                $signatureValid,
            sourceIp: $sourceIp,
            userAgent: $userAgent
        );

        if (
            $event->status
            === PaymentGatewayEventStatus::Processed
        ) {
            return $event;
        }

        /*
         * A spoofed request must not be able to permanently reserve a
         * legitimate provider event ID. If the previously recorded event
         * was rejected only with an invalid signature, a later valid
         * delivery may safely take ownership of that event key.
         */
        if (
            $signatureValid
            && $event->status
                === PaymentGatewayEventStatus::Rejected
            && $event->signature_valid === false
        ) {
            $event->update([
                'signature_valid' => true,
                'status' =>
                    PaymentGatewayEventStatus::Received,
                'request_payload' =>
                    $this
                        ->sanitizer
                        ->sanitize(
                            $payload
                        ),
                'payload_hash' =>
                    $payloadHash,
                'authority' =>
                    $authority,
                'source_ip' =>
                    $sourceIp,
                'user_agent' =>
                    $userAgent
                        ? mb_substr(
                            $userAgent,
                            0,
                            500
                        )
                        : null,
                'error_message' => null,
            ]);

            $event->refresh();
        }

        if (! $signatureValid) {
            $this->reject(
                $event,
                'Invalid or expired gateway webhook signature.'
            );

            throw new HttpException(
                401,
                'Invalid gateway webhook signature.'
            );
        }

        if (! $authority) {
            $this->reject(
                $event,
                'Webhook does not contain an authority/token.'
            );

            throw ValidationException::withMessages([
                'authority' =>
                    'Gateway webhook does not contain an authority/token.',
            ]);
        }

        $transaction = PaymentTransaction::query()
            ->where(
                'gateway',
                $gatewayName
            )
            ->where(
                'authority',
                $authority
            )
            ->first();

        if (! $transaction) {
            $this->reject(
                $event,
                'No PaymentTransaction matches this webhook authority.'
            );

            throw ValidationException::withMessages([
                'authority' =>
                    'Unknown gateway authority.',
            ]);
        }

        return $this->process(
            $event,
            $transaction
        );
    }

    private function process(
        PaymentGatewayEvent $event,
        PaymentTransaction $transaction
    ): PaymentGatewayEvent {
        if (
            $event->status
            === PaymentGatewayEventStatus::Rejected
        ) {
            return $event;
        }

        try {
            $event->update([
                'payment_transaction_id' =>
                    $transaction->getKey(),
                'attempts' =>
                    (int) $event->attempts + 1,
                'status' =>
                    PaymentGatewayEventStatus::Received,
                'error_message' => null,
            ]);

            $this->verification->verify(
                $transaction
            );

            $event->update([
                'status' =>
                    PaymentGatewayEventStatus::Processed,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return $event->refresh();
        } catch (\Throwable $exception) {
            $event->update([
                'status' =>
                    PaymentGatewayEventStatus::Failed,
                'error_message' =>
                    mb_substr(
                        $exception->getMessage(),
                        0,
                        5000
                    ),
            ]);

            throw $exception;
        }
    }

    private function event(
        string $gateway,
        string $eventKey,
        PaymentGatewayEventType $type,
        ?string $authority,
        array $payload,
        string $payloadHash,
        ?bool $signatureValid,
        ?string $sourceIp,
        ?string $userAgent
    ): PaymentGatewayEvent {
        $attributes = [
            'uuid' => (string) Str::uuid(),
            'gateway' => $gateway,
            'event_key' => $eventKey,
            'event_type' => $type,
            'authority' => $authority,
            'payload_hash' =>
                $payloadHash,
            'request_payload' =>
                $this
                    ->sanitizer
                    ->sanitize(
                        $payload
                    ),
            'signature_valid' =>
                $signatureValid,
            'source_ip' =>
                $sourceIp,
            'user_agent' =>
                $userAgent
                    ? mb_substr(
                        $userAgent,
                        0,
                        500
                    )
                    : null,
            'status' =>
                PaymentGatewayEventStatus::Received,
            'attempts' => 0,
            'received_at' => now(),
        ];

        try {
            return PaymentGatewayEvent::query()
                ->create($attributes)
                ->refresh();
        } catch (QueryException $exception) {
            /*
             * Concurrent callback/webhook deliveries can race on the
             * UNIQUE(gateway,event_key) constraint. The winner owns the
             * event row; all other requests reuse it.
             */
            $existing =
                PaymentGatewayEvent::query()
                    ->where(
                        'gateway',
                        $gateway
                    )
                    ->where(
                        'event_key',
                        $eventKey
                    )
                    ->first();

            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function reject(
        PaymentGatewayEvent $event,
        string $message
    ): void {
        $event->update([
            'status' =>
                PaymentGatewayEventStatus::Rejected,
            'error_message' => $message,
        ]);
    }

    private function hashPayload(
        array $payload
    ): string {
        ksort($payload);

        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            )
        );
    }
}
