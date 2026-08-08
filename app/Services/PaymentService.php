<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentVerified;
use App\Models\Payment;
use App\Models\UnitInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function verify(Payment $payment, User $verifiedBy): Payment
    {
        return DB::transaction(function () use ($payment, $verifiedBy): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Payment cannot be verified in its current status.',
                ]);
            }

            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $payment->paid_at ?? now(),
                'verified_at' => now(),
                'verified_by' => $verifiedBy->getKey(),
            ]);

            foreach ($payment->paymentAllocations()->get() as $allocation) {
                if ($allocation->payable_type !== (new UnitInvoice())->getMorphClass()) {
                    continue;
                }

                $invoice = UnitInvoice::query()->lockForUpdate()->find($allocation->payable_id);

                if (! $invoice) {
                    continue;
                }

                $paid = min(
                    (int) $invoice->total_amount,
                    (int) $invoice->paid_amount + (int) $allocation->amount,
                );

                $invoice->update([
                    'paid_amount' => $paid,
                    'outstanding_amount' => max(0, (int) $invoice->total_amount - $paid),
                    'status' => $paid >= $invoice->total_amount
                        ? InvoiceStatus::Paid
                        : InvoiceStatus::Partial,
                ]);
            }

            $verified = $payment->refresh();

            DB::afterCommit(
                fn () => PaymentVerified::dispatch($verified)
            );

            return $verified;
        }, 3);
    }
}
