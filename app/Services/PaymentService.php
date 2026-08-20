<?php

namespace App\Services;

use App\Data\Payments\GatewayVerificationResult;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentVerified;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Models\WalletTopUp;
use App\Services\Payments\GatewayPayloadSanitizer;
use App\Services\Wallet\WalletTopUpService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly WalletTopUpService $walletTopUps,
        private readonly GatewayPayloadSanitizer $gatewayPayloads,
        private readonly InvoiceInstallmentService $installments
    ) {
    }

    public function createForInvoice(
        UnitInvoice $invoice,
        User $payer,
        array $data
    ): Payment {
        $amount = (int) $data['amount'];
        $idempotencyKey = $this->normalizeIdempotencyKey(
            $data['idempotency_key'] ?? null
        );

        try {
            return DB::transaction(function () use (
                $invoice,
                $payer,
                $data,
                $amount,
                $idempotencyKey
            ): Payment {
                $invoice = UnitInvoice::query()
                    ->with('building')
                    ->lockForUpdate()
                    ->findOrFail($invoice->getKey());

                if ($idempotencyKey !== null) {
                    $existing = Payment::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        return $this->assertIdempotentInvoicePayment(
                            $existing,
                            $invoice,
                            $payer,
                            $data,
                            $amount
                        );
                    }
                }

                if (! in_array(
                    $invoice->status,
                    [
                        InvoiceStatus::Issued,
                        InvoiceStatus::Partial,
                        InvoiceStatus::Overdue,
                    ],
                    true
                )) {
                    throw ValidationException::withMessages([
                        'invoice' =>
                            'Only an issued, partial or overdue invoice can be paid.',
                    ]);
                }

                $reservedAmount = $this->reservedInvoiceAmount(
                    $invoice
                );

                $availableAmount = max(
                    0,
                    (int) $invoice->outstanding_amount
                    - $reservedAmount
                );

                if (
                    $amount <= 0
                    || $amount > $availableAmount
                ) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Payment amount exceeds the currently available invoice outstanding amount.',
                    ]);
                }

                $payment = Payment::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'building_id' => $invoice->building_id,
                    'payer_user_id' => $payer->getKey(),
                    'payment_number' => sprintf(
                        'PAY-%d-%s',
                        $invoice->building_id,
                        strtoupper(Str::random(12))
                    ),
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $amount,
                    'currency' => $invoice->building?->currency ?: 'IRR',
                    'method' => $data['method'],
                    'status' => PaymentStatus::Pending,
                    'description' => $data['description'] ?? null,
                ]);

                $payment->paymentAllocations()->create([
                    'payable_type' => $invoice->getMorphClass(),
                    'payable_id' => $invoice->getKey(),
                    'amount' => $amount,
                ]);

                return $payment->refresh();
            }, 3);
        } catch (QueryException $exception) {
            if ($idempotencyKey === null) {
                throw $exception;
            }

            $existing = Payment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            $invoice = UnitInvoice::query()
                ->with('building')
                ->findOrFail($invoice->getKey());

            return $this->assertIdempotentInvoicePayment(
                $existing,
                $invoice,
                $payer,
                $data,
                $amount
            );
        }
    }

    private function reservedInvoiceAmount(
        UnitInvoice $invoice
    ): int {
        $payableTypes = array_values(array_unique([
            $invoice->getMorphClass(),
            UnitInvoice::class,
        ]));

        return (int) PaymentAllocation::query()
            ->join(
                'payments',
                'payments.id',
                '=',
                'payment_allocations.payment_id'
            )
            ->whereIn(
                'payment_allocations.payable_type',
                $payableTypes
            )
            ->where(
                'payment_allocations.payable_id',
                $invoice->getKey()
            )
            ->whereIn(
                'payments.status',
                [
                    PaymentStatus::Pending->value,
                    PaymentStatus::Processing->value,
                    PaymentStatus::Failed->value,
                ]
            )
            ->sum('payment_allocations.amount');
    }

    private function assertIdempotentInvoicePayment(
        Payment $payment,
        UnitInvoice $invoice,
        User $payer,
        array $data,
        int $amount
    ): Payment {
        $allocation = $payment
            ->paymentAllocations()
            ->whereIn(
                'payable_type',
                array_values(array_unique([
                    $invoice->getMorphClass(),
                    UnitInvoice::class,
                ]))
            )
            ->where('payable_id', $invoice->getKey())
            ->first();

        $requestedMethod = $data['method'] instanceof \BackedEnum
            ? $data['method']->value
            : (string) $data['method'];

        $storedMethod = $payment->method instanceof \BackedEnum
            ? $payment->method->value
            : (string) $payment->method;

        if (
            (int) $payment->building_id !== (int) $invoice->building_id
            || (int) $payment->payer_user_id !== (int) $payer->getKey()
            || (int) $payment->amount !== $amount
            || $storedMethod !== $requestedMethod
            || ! $allocation
            || (int) $allocation->amount !== $amount
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' =>
                    'The idempotency key has already been used for a different payment operation.',
            ]);
        }

        return $payment->refresh();
    }

    private function normalizeIdempotencyKey(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function verify(
        Payment $payment,
        User $verifiedBy
    ): Payment {
        return $this->verifyInternal(
            $payment,
            $verifiedBy
        );
    }

    public function verifyFromGateway(
        Payment $payment,
        PaymentTransaction $transaction,
        GatewayVerificationResult $result
    ): Payment {
        return $this->verifyInternal(
            $payment,
            null,
            $transaction,
            $result
        );
    }

    private function verifyInternal(
        Payment $payment,
        ?User $verifiedBy = null,
        ?PaymentTransaction $gatewayTransaction = null,
        ?GatewayVerificationResult $gatewayResult = null
    ): Payment {
        $retryTopUpId = null;
        $retryActorId = null;

        $verified = DB::transaction(function () use (
            $payment,
            $verifiedBy,
            $gatewayTransaction,
            $gatewayResult,
            &$retryTopUpId,
            &$retryActorId
        ): Payment {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail(
                    $payment->getKey()
                );

            if ($gatewayTransaction) {
                $gatewayTransaction =
                    PaymentTransaction::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $gatewayTransaction
                                ->getKey()
                        );

                if (
                    (int) $gatewayTransaction
                        ->payment_id
                    !== (int) $payment
                        ->getKey()
                ) {
                    throw ValidationException::withMessages([
                        'gateway_transaction' =>
                            'Gateway transaction does not belong to this payment.',
                    ]);
                }
            }

            /*
             * Repeated callback/webhook delivery is expected in real
             * gateways. A paid Payment is final, but we still normalize
             * the specific gateway transaction metadata idempotently.
             */
            if (
                $payment->status
                === PaymentStatus::Paid
            ) {
                if ($gatewayTransaction) {
                    $this->markGatewayTransactionVerified(
                        $gatewayTransaction,
                        $gatewayResult
                    );
                }

                return $payment;
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
                    'status' =>
                        'Payment cannot be verified in its current status.',
                ]);
            }

            $topUp = WalletTopUp::query()
                ->where(
                    'payment_id',
                    $payment->getKey()
                )
                ->lockForUpdate()
                ->first();

            if ($topUp) {
                /*
                 * A Wallet Top-up is an external payment whose allocation
                 * target is a wallet, not a UnitInvoice.
                 */
                $this->walletTopUps->credit(
                    $topUp,
                    $verifiedBy
                );

                $retryTopUpId =
                    $topUp->getKey();

                $retryActorId =
                    $payment->payer_user_id
                    ?: $verifiedBy?->getKey();
            } else {
                $this->applyInvoiceAllocations(
                    $payment
                );
            }

            $payment->update([
                'status' =>
                    PaymentStatus::Paid,

                'paid_at' =>
                    $payment->paid_at
                    ?? now(),

                'verified_at' => now(),

                /*
                 * Gateway callbacks are unauthenticated machine events,
                 * therefore verified_by remains null. Manual privileged
                 * verification still records the operator.
                 */
                'verified_by' =>
                    $verifiedBy?->getKey(),
            ]);

            if ($gatewayTransaction) {
                $this->markGatewayTransactionVerified(
                    $gatewayTransaction,
                    $gatewayResult
                );
            } else {
                /*
                 * Backward-compatible manual verification path.
                 */
                $transaction = $payment
                    ->paymentTransactions()
                    ->whereNull('verified_at')
                    ->whereNull('failed_at')
                    ->latest('id')
                    ->first();

                if ($transaction) {
                    $transaction->update([
                        'verified_at' => now(),
                    ]);
                }
            }

            $verified = $payment->refresh();

            DB::afterCommit(
                fn () =>
                    PaymentVerified::dispatch(
                        $verified
                    )
            );

            return $verified;
        }, 3);

        /*
         * Auto debt collection is deliberately outside payment
         * verification. A successful PSP payment and Wallet credit must
         * remain final even when debt retry fails.
         */
        if (
            $retryTopUpId !== null
            && $retryActorId !== null
        ) {
            $topUp = WalletTopUp::query()
                ->find(
                    $retryTopUpId
                );

            $actor = User::query()
                ->find(
                    $retryActorId
                );

            if ($topUp && $actor) {
                $this->walletTopUps
                    ->retryOutstanding(
                        $topUp,
                        $actor
                    );
            }
        }

        return $verified->refresh();
    }

    private function markGatewayTransactionVerified(
        PaymentTransaction $transaction,
        ?GatewayVerificationResult $result
    ): void {
        $existingResponse = is_array(
            $transaction->response_payload
        )
            ? $transaction->response_payload
            : [];

        $transaction->update([
            'gateway_transaction_id' =>
                $result?->gatewayTransactionId
                ?: $transaction
                    ->gateway_transaction_id,

            'tracking_code' =>
                $result?->trackingCode
                ?: $transaction
                    ->tracking_code,

            'reference_number' =>
                $result?->referenceNumber
                ?: $transaction
                    ->reference_number,

            'response_payload' => [
                ...$existingResponse,
                'verification' => [
                    'successful' =>
                        $result?->successful
                        ?? true,

                    'amount' =>
                        $result?->amount,

                    'currency' =>
                        $result?->currency,

                    'merchant_reference' =>
                        $result
                            ?->merchantReference,

                    'gateway_response' =>
                        $this
                            ->gatewayPayloads
                            ->sanitize(
                                $result?->raw
                                ?? []
                            ),
                ],
            ],

            'verified_at' =>
                $transaction->verified_at
                ?? now(),

            'failed_at' => null,
        ]);
    }

    private function applyInvoiceAllocations(
        Payment $payment
    ): void {
        $allocations = $payment
            ->paymentAllocations()
            ->lockForUpdate()
            ->get();

        $allocatedAmount = (int) $allocations->sum(
            'amount'
        );

        if (
            $allocatedAmount <= 0
            || $allocatedAmount !== (int) $payment->amount
        ) {
            throw ValidationException::withMessages([
                'allocations' =>
                    'The full payment amount must be allocated before verification.',
            ]);
        }

        $updates = [];

        foreach ($allocations as $allocation) {
            if (
                $allocation->payable_type
                    !== (new UnitInvoice())->getMorphClass()
                && $allocation->payable_type
                    !== UnitInvoice::class
            ) {
                throw ValidationException::withMessages([
                    'allocations' =>
                        'Unsupported payment allocation target.',
                ]);
            }

            $invoice = UnitInvoice::query()
                ->lockForUpdate()
                ->findOrFail(
                    $allocation->payable_id
                );

            if (
                (int) $invoice->building_id
                !== (int) $payment->building_id
            ) {
                throw ValidationException::withMessages([
                    'allocations' =>
                        'Payment and invoice must belong to the same building.',
                ]);
            }

            if (! in_array(
                $invoice->status,
                [
                    InvoiceStatus::Issued,
                    InvoiceStatus::Partial,
                    InvoiceStatus::Overdue,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'invoice' =>
                        'Allocated invoice is not payable.',
                ]);
            }

            if (
                (int) $allocation->amount
                > (int) $invoice->outstanding_amount
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'Allocated amount exceeds invoice outstanding amount.',
                ]);
            }

            $newPaid = (int) $invoice->paid_amount
                + (int) $allocation->amount;

            $newOutstanding = max(
                0,
                (int) $invoice->total_amount - $newPaid
            );

            $updates[] = [
                'invoice' => $invoice,
                'applied_amount' => (int) $allocation->amount,
                'paid_amount' => $newPaid,
                'outstanding_amount' => $newOutstanding,
                'status' => $newOutstanding === 0
                    ? InvoiceStatus::Paid
                    : InvoiceStatus::Partial,
            ];
        }

        foreach ($updates as $update) {
            $this->installments->applyPayment(
                $update['invoice'],
                $update['applied_amount']
            );

            $update['invoice']->update([
                'paid_amount' => $update['paid_amount'],
                'outstanding_amount' =>
                    $update['outstanding_amount'],
                'status' => $update['status'],
            ]);
        }
    }
}
