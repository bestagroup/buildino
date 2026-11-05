<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Unit;
use App\Models\UnitInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Non-cash set-off: a unit credit reduces invoice totals without fabricating payments. */
final class InvoiceCreditService
{
    public function apply(int $unitId, int $buildingId): void
    {
        DB::transaction(function () use ($unitId, $buildingId): void {
            Unit::whereKey($unitId)->lockForUpdate()->firstOrFail();
            $invoices = UnitInvoice::where('unit_id', $unitId)->where('building_id', $buildingId)
                ->orderBy('id')->lockForUpdate()->get();
            $open = [InvoiceStatus::Issued, InvoiceStatus::Partial, InvoiceStatus::Overdue];
            $credits = $invoices->filter(fn ($invoice) => in_array($invoice->status, $open, true)
                && $invoice->total_amount < 0 && $invoice->credit_remaining_amount > 0);
            $debits = $invoices->filter(fn ($invoice) => in_array($invoice->status, $open, true)
                && $invoice->total_amount > 0 && $invoice->outstanding_amount > 0);

            foreach ($credits as $credit) {
                foreach ($debits as $debit) {
                    $amount = min($credit->credit_remaining_amount, $debit->outstanding_amount);
                    if ($amount <= 0) {
                        continue;
                    }
                    if ($debit->invoiceInstallments()->where('status', '!=', 'cancelled')->exists()
                        || $debit->paymentAllocations()->whereHas('payment', fn ($query) => $query->whereIn('status', ['pending', 'processing']))->exists()) {
                        throw ValidationException::withMessages([
                            'credit' => 'برای اعمال بستانکاری، ابتدا پرداخت در انتظار یا برنامه اقساط صورتحساب '.$debit->invoice_number.' را تعیین تکلیف کنید.',
                        ]);
                    }
                    $existing = DB::table('invoice_credit_allocations')
                        ->where('credit_invoice_id', $credit->id)->where('debit_invoice_id', $debit->id)->first();
                    DB::table('invoice_credit_allocations')->updateOrInsert(
                        ['credit_invoice_id' => $credit->id, 'debit_invoice_id' => $debit->id],
                        ['amount' => (int) ($existing?->amount ?? 0) + $amount,
                            'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]
                    );
                    $debit->forceFill(['credit_applied_amount' => $debit->credit_applied_amount + $amount])->save();
                    app(InvoiceService::class)->recalculate($debit);
                    $credit->forceFill([
                        'credit_remaining_amount' => $credit->credit_remaining_amount - $amount,
                        'status' => $credit->credit_remaining_amount === $amount ? InvoiceStatus::Paid : InvoiceStatus::Issued,
                    ])->save();
                }
            }
        }, 3);
    }
}
