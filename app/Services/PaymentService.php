<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentVerified;
use App\Models\Payment;
use App\Models\UnitInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
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
        return DB::transaction(function () use (
            $payment,
            $verifiedBy
        ): Payment {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            if (! in_array(
                $payment->status,
                [
                    PaymentStatus::Pending,
                    PaymentStatus::Processing,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Payment cannot be verified in its current status.',
                ]);
            }

            $allocations = $payment
                ->paymentAllocations()
                ->lockForUpdate()
                ->get();

            $allocatedAmount = (int) $allocations->sum('amount');

            if (
                $allocatedAmount <= 0
                || $allocatedAmount !== (int) $payment->amount
            ) {
                throw ValidationException::withMessages([
                    'allocations' => 'The full payment amount must be allocated before verification.',
                ]);
            }

            $updates = [];

            foreach ($allocations as $allocation) {
                if (
                    $allocation->payable_type !== (new UnitInvoice())->getMorphClass()
                    && $allocation->payable_type !== UnitInvoice::class
                ) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Unsupported payment allocation target.',
                    ]);
                }

                $invoice = UnitInvoice::query()
                    ->lockForUpdate()
                    ->findOrFail($allocation->payable_id);

                if (
                    (int) $invoice->building_id
                    !== (int) $payment->building_id
                ) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Payment and invoice must belong to the same building.',
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
                        'invoice' => 'Allocated invoice is not payable.',
                    ]);
                }

                if (
                    (int) $allocation->amount
                    > (int) $invoice->outstanding_amount
                ) {
                    throw ValidationException::withMessages([
                        'amount' => 'Allocated amount exceeds invoice outstanding amount.',
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
                    'outstanding_amount' => $update['outstanding_amount'],
                    'status' => $update['status'],
                ]);
            }

            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $payment->paid_at ?? now(),
                'verified_at' => now(),
                'verified_by' => $verifiedBy->getKey(),
            ]);

            $verified = $payment->refresh();

            DB::afterCommit(
                fn () => PaymentVerified::dispatch($verified)
            );

            return $verified;
        }, 3);
    }
}
