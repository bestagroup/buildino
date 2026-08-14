<?php

namespace App\Services;

use App\Data\Payments\GatewayVerificationResult;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentVerified;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Models\WalletTopUp;
use App\Services\Payments\GatewayPayloadSanitizer;
use App\Services\Wallet\WalletTopUpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly WalletTopUpService $walletTopUps,
        private readonly GatewayPayloadSanitizer $gatewayPayloads
    ) {
    }

    public function createForInvoice(
        UnitInvoice $invoice,
        User $payer,
        array $data
    ): Payment {
        $invoice->loadMissing('building');

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
                'invoice' => 'Only an issued, partial or overdue invoice can be paid.',
            ]);
        }

        $amount = (int) $data['amount'];

        if (
            $amount <= 0
            || $amount > (int) $invoice->outstanding_amount
        ) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero and cannot exceed the invoice outstanding amount.',
            ]);
        }

        return DB::transaction(function () use (
            $invoice,
            $payer,
            $data,
            $amount
        ): Payment {
            $payment = Payment::query()->create([
                'uuid' => (string) Str::uuid(),
                'building_id' => $invoice->building_id,
                'payer_user_id' => $payer->getKey(),
                'payment_number' => sprintf(
                    'PAY-%d-%s',
                    $invoice->building_id,
                    strtoupper(Str::random(12))
                ),
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
        });
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
                'paid_amount' => $newPaid,
                'outstanding_amount' => $newOutstanding,
                'status' => $newOutstanding === 0
                    ? InvoiceStatus::Paid
                    : InvoiceStatus::Partial,
            ];
        }

        foreach ($updates as $update) {
            $update['invoice']->update([
                'paid_amount' => $update['paid_amount'],
                'outstanding_amount' =>
                    $update['outstanding_amount'],
                'status' => $update['status'],
            ]);
        }
    }
}
