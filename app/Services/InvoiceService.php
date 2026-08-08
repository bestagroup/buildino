<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\UnitInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function recalculate(UnitInvoice $invoice): UnitInvoice
    {
        return DB::transaction(function () use ($invoice): UnitInvoice {
            $invoice->refresh();
            $subtotal = (int) $invoice->invoiceItems()->sum('total_amount');
            $total = max(0, $subtotal - (int) $invoice->discount_amount + (int) $invoice->penalty_amount);
            $paid = (int) $invoice->paid_amount;

            $invoice->update([
                'subtotal' => $subtotal,
                'total_amount' => $total,
                'outstanding_amount' => max(0, $total - $paid),
                'status' => $paid >= $total && $total > 0 ? InvoiceStatus::Paid : ($paid > 0 ? InvoiceStatus::Partial : $invoice->status),
            ]);

            return $invoice->refresh();
        });
    }

    public function issue(UnitInvoice $invoice): UnitInvoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only draft invoices can be issued.']);
        }

        $invoice = $this->recalculate($invoice);
        $invoice->update(['status' => InvoiceStatus::Issued]);
        return $invoice->refresh();
    }
}
