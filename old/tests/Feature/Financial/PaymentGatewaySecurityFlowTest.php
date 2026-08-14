<?php

namespace Tests\Feature\Financial;

use App\Enums\PaymentGatewayEventStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTopUpStatus;
use App\Enums\WalletTransferType;
use App\Models\PaymentGatewayEvent;
use App\Models\PaymentTransaction;
use App\Models\WalletTransfer;
use App\Services\Payments\PaymentGatewayCallbackService;
use App\Services\Payments\PaymentGatewayInitiationService;
use App\Services\Wallet\WalletTopUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class PaymentGatewaySecurityFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment_gateways.gateways.fake' => [
                'driver' => 'fake',
                'enabled' => true,
                'webhook_secret' =>
                    'buildino-test-secret',
            ],
            'payment_gateways.webhook_max_skew_seconds' =>
                300,
            'payment_gateways.callback_base_url' =>
                'https://buildino.test',
        ]);
    }

    public function test_gateway_initiation_is_idempotent_and_server_assigns_authority(): void
    {
        [
            $topUp,
            $payer,
        ] = $this->pendingTopUp(
            500_000,
            'gateway-initiation-key'
        );

        $payment = $topUp
            ->payment()
            ->firstOrFail();

        $this->assertNull(
            $payment
                ->paymentTransactions()
                ->firstOrFail()
                ->authority
        );

        $service = app(
            PaymentGatewayInitiationService::class
        );

        $transaction = $service->initiate(
            $payment,
            'fake',
            'gateway-initiation-key',
            $payer
        );

        $this->assertSame(
            'fake',
            $transaction->gateway
        );

        $this->assertNotNull(
            $transaction->authority
        );

        $this->assertStringStartsWith(
            'FAKE-',
            $transaction->authority
        );

        $this->assertNotEmpty(
            $transaction
                ->response_payload[
                    'redirect_url'
                ]
                ?? null
        );

        $this->assertSame(
            PaymentStatus::Processing,
            $payment->fresh()->status
        );

        $again = $service->initiate(
            $payment->fresh(),
            'fake',
            'gateway-initiation-key',
            $payer
        );

        $this->assertSame(
            $transaction->id,
            $again->id
        );

        $this->assertSame(
            1,
            PaymentTransaction::query()
                ->where(
                    'payment_id',
                    $payment->id
                )
                ->count()
        );
    }

    public function test_repeated_browser_callback_verifies_server_side_and_credits_wallet_once(): void
    {
        [
            $topUp,
            $payer,
        ] = $this->pendingTopUp(
            500_000,
            'gateway-callback-key'
        );

        $payment =
            $topUp->payment()
                ->firstOrFail();

        $transaction = app(
            PaymentGatewayInitiationService::class
        )->initiate(
            $payment,
            'fake',
            'gateway-callback-key',
            $payer
        );

        $callback = app(
            PaymentGatewayCallbackService::class
        );

        $payload = [
            'authority' =>
                $transaction->authority,
            /*
             * This value is intentionally untrusted.
             * FakePaymentGateway::verify() is still called server-side.
             */
            'status' => 'success',
        ];

        $first = $callback->callback(
            'fake',
            $payload,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertSame(
            PaymentGatewayEventStatus::Processed,
            $first->status
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->fresh()->status
        );

        $this->assertSame(
            WalletTopUpStatus::Credited,
            $topUp->fresh()->status
        );

        $this->assertSame(
            500_000,
            (int) $topUp
                ->wallet
                ->fresh()
                ->balance
        );

        $second = $callback->callback(
            'fake',
            $payload,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            500_000,
            (int) $topUp
                ->wallet
                ->fresh()
                ->balance
        );

        $this->assertSame(
            1,
            WalletTransfer::query()
                ->where(
                    'type',
                    WalletTransferType::TopUp->value
                )
                ->where(
                    'destination_wallet_id',
                    $topUp->wallet_id
                )
                ->count()
        );
    }

    public function test_invalid_webhook_signature_is_rejected_without_touching_money(): void
    {
        [
            $topUp,
            $payer,
        ] = $this->pendingTopUp(
            300_000,
            'gateway-invalid-webhook-key'
        );

        $payment =
            $topUp->payment()
                ->firstOrFail();

        $transaction = app(
            PaymentGatewayInitiationService::class
        )->initiate(
            $payment,
            'fake',
            'gateway-invalid-webhook-key',
            $payer
        );

        $raw = json_encode([
            'event_id' => 'evt-invalid-1',
            'authority' =>
                $transaction->authority,
        ], JSON_THROW_ON_ERROR);

        $timestamp = (string) time();

        try {
            app(
                PaymentGatewayCallbackService::class
            )->webhook(
                'fake',
                $raw,
                json_decode(
                    $raw,
                    true,
                    flags: JSON_THROW_ON_ERROR
                ),
                [
                    'X-Event-Id' => [
                        'evt-invalid-1',
                    ],
                    'X-Timestamp' => [
                        $timestamp,
                    ],
                    'X-Signature' => [
                        'invalid-signature',
                    ],
                ],
                '127.0.0.1',
                'PHPUnit'
            );

            $this->fail(
                'Invalid webhook signature was accepted.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                401,
                $exception->getStatusCode()
            );
        }

        $event = PaymentGatewayEvent::query()
            ->where(
                'event_key',
                'webhook:evt-invalid-1'
            )
            ->firstOrFail();

        $this->assertSame(
            PaymentGatewayEventStatus::Rejected,
            $event->status
        );

        $this->assertFalse(
            (bool) $event->signature_valid
        );

        $this->assertSame(
            PaymentStatus::Processing,
            $payment->fresh()->status
        );

        $this->assertSame(
            0,
            (int) $topUp
                ->wallet
                ->fresh()
                ->balance
        );

        /*
         * A spoofed request must not be able to reserve a provider event
         * ID forever. A later valid signature for the same event is
         * accepted and reuses the existing audit row.
         */
        $validSignature = hash_hmac(
            'sha256',
            $timestamp.'.'.$raw,
            'buildino-test-secret'
        );

        $recovered = app(
            PaymentGatewayCallbackService::class
        )->webhook(
            'fake',
            $raw,
            json_decode(
                $raw,
                true,
                flags: JSON_THROW_ON_ERROR
            ),
            [
                'X-Event-Id' => [
                    'evt-invalid-1',
                ],
                'X-Timestamp' => [
                    $timestamp,
                ],
                'X-Signature' => [
                    $validSignature,
                ],
            ],
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertSame(
            $event->id,
            $recovered->id
        );

        $this->assertSame(
            PaymentGatewayEventStatus::Processed,
            $recovered->status
        );

        $this->assertSame(
            300_000,
            (int) $topUp
                ->wallet
                ->fresh()
                ->balance
        );
    }

    public function test_signed_webhook_is_replay_safe_and_credits_once(): void
    {
        [
            $topUp,
            $payer,
        ] = $this->pendingTopUp(
            700_000,
            'gateway-valid-webhook-key'
        );

        $payment =
            $topUp->payment()
                ->firstOrFail();

        $transaction = app(
            PaymentGatewayInitiationService::class
        )->initiate(
            $payment,
            'fake',
            'gateway-valid-webhook-key',
            $payer
        );

        $raw = json_encode([
            'event_id' => 'evt-valid-1',
            'authority' =>
                $transaction->authority,
        ], JSON_THROW_ON_ERROR);

        $timestamp = (string) time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$raw,
            'buildino-test-secret'
        );

        $headers = [
            'X-Event-Id' => [
                'evt-valid-1',
            ],
            'X-Timestamp' => [
                $timestamp,
            ],
            'X-Signature' => [
                $signature,
            ],
        ];

        $service = app(
            PaymentGatewayCallbackService::class
        );

        $first = $service->webhook(
            'fake',
            $raw,
            json_decode(
                $raw,
                true,
                flags: JSON_THROW_ON_ERROR
            ),
            $headers,
            '127.0.0.1',
            'PHPUnit'
        );

        $second = $service->webhook(
            'fake',
            $raw,
            json_decode(
                $raw,
                true,
                flags: JSON_THROW_ON_ERROR
            ),
            $headers,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            PaymentGatewayEventStatus::Processed,
            $first->status
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->fresh()->status
        );

        $this->assertSame(
            700_000,
            (int) $topUp
                ->wallet
                ->fresh()
                ->balance
        );

        $this->assertSame(
            1,
            PaymentGatewayEvent::query()
                ->where(
                    'event_key',
                    'webhook:evt-valid-1'
                )
                ->count()
        );

        $this->assertSame(
            1,
            WalletTransfer::query()
                ->where(
                    'type',
                    WalletTransferType::TopUp->value
                )
                ->where(
                    'destination_wallet_id',
                    $topUp->wallet_id
                )
                ->count()
        );
    }

    public function test_gateway_amount_mismatch_fails_closed_and_same_event_can_retry_after_gateway_recovers(): void
    {
        [
            $topUp,
            $payer,
        ] = $this->pendingTopUp(
            900_000,
            'gateway-mismatch-key'
        );

        $payment =
            $topUp->payment()
                ->firstOrFail();

        $transaction = app(
            PaymentGatewayInitiationService::class
        )->initiate(
            $payment,
            'fake',
            'gateway-mismatch-key',
            $payer
        );

        config([
            'payment_gateways.gateways.fake.verification.amount'
                => 899_999,
        ]);

        $service = app(
            PaymentGatewayCallbackService::class
        );

        $payload = [
            'authority' =>
                $transaction->authority,
        ];

        try {
            $service->callback(
                'fake',
                $payload,
                '127.0.0.1',
                'PHPUnit'
            );

            $this->fail(
                'Gateway amount mismatch was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'amount',
                $exception->errors()
            );
        }

        $event = PaymentGatewayEvent::query()
            ->where(
                'event_type',
                'callback'
            )
            ->firstOrFail();

        $this->assertSame(
            PaymentGatewayEventStatus::Failed,
            $event->status
        );

        $this->assertSame(
            PaymentStatus::Processing,
            $payment->fresh()->status
        );

        $this->assertSame(
            0,
            (int) $topUp
                ->wallet
                ->fresh()
                ->balance
        );

        /*
         * Gateway/provider verification recovers. The SAME callback event
         * can be retried; no new Payment or Wallet credit is created.
         */
        config([
            'payment_gateways.gateways.fake.verification.amount'
                => 900_000,
        ]);

        $retried = $service->callback(
            'fake',
            $payload,
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertSame(
            $event->id,
            $retried->id
        );

        $this->assertSame(
            PaymentGatewayEventStatus::Processed,
            $retried->status
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->fresh()->status
        );

        $this->assertSame(
            900_000,
            (int) $topUp
                ->wallet
                ->fresh()
                ->balance
        );
    }

    private function pendingTopUp(
        int $amount,
        string $idempotencyKey
    ): array {
        $graph =
            $this->createBuildingGraph();

        $payer = $this->createUser();

        $topUp = app(
            WalletTopUpService::class
        )->create(
            $graph['building'],
            $payer,
            $payer,
            [
                'amount' => $amount,
                'method' =>
                    PaymentMethod::Online,
                'gateway' => 'fake',
                'idempotency_key' =>
                    $idempotencyKey,
                /*
                 * Even if a hostile client attempts to inject authority,
                 * WalletTopUpService now ignores it.
                 */
                'authority' =>
                    'CLIENT-MUST-NOT-WIN',
            ]
        );

        $topUp->load([
            'payment',
            'wallet',
        ]);

        return [
            $topUp,
            $payer,
        ];
    }
}
