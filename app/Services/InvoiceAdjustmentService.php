<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\FinancialAuditLog;
use App\Models\UnitInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InvoiceAdjustmentService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /** Replace the invoice's signed adjustment; retries never accumulate it. */
    public function set(UnitInvoice $invoice, User $actor, int $amount, int $expectedAmount, string $reason): UnitInvoice
    {
        return DB::transaction(function () use ($invoice, $actor, $amount, $expectedAmount, $reason): UnitInvoice {
            $invoice = UnitInvoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            $reason = trim($reason);

            if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Issued, InvoiceStatus::Partial, InvoiceStatus::Overdue, InvoiceStatus::Paid], true)) {
                throw ValidationException::withMessages(['invoice' => 'صورتحساب لغوشده یا باطل‌شده قابل تعدیل نیست.']);
            }
            if ($amount === (int) $invoice->adjustment_amount && $reason === $invoice->adjustment_reason) {
                return $invoice;
            }
            if ($expectedAmount !== (int) $invoice->adjustment_amount) {
                throw ValidationException::withMessages(['amount' => 'تعدیل صورتحساب تغییر کرده است؛ اطلاعات را تازه کنید.']);
            }
            if ($invoice->invoiceInstallments()->where('status', '!=', 'cancelled')->exists()) {
                throw ValidationException::withMessages(['invoice' => 'برای تعدیل، ابتدا برنامه اقساط را لغو کنید و پس از تعدیل دوباره تنظیم کنید.']);
            }
            if ($invoice->paymentAllocations()->whereHas('payment', fn ($query) => $query->whereIn('status', ['pending', 'processing']))->exists()) {
                throw ValidationException::withMessages(['invoice' => 'ابتدا پرداخت در انتظار یا در حال پردازش این صورتحساب را تعیین تکلیف کنید.']);
            }
            $total = (int) $invoice->subtotal - (int) $invoice->discount_amount + (int) $invoice->penalty_amount + $amount;
            if ($total < (int) $invoice->paid_amount || $total < 0 || $total > 9007199254740991) {
                throw ValidationException::withMessages(['amount' => 'مبلغ نهایی نباید منفی، کمتر از پرداخت ثبت‌شده یا خارج از محدوده مجاز باشد.']);
            }

            $old = $invoice->only(['adjustment_amount', 'adjustment_reason', 'total_amount', 'outstanding_amount', 'status']);
            $invoice->update(['adjustment_amount' => $amount, 'adjustment_reason' => $reason]);
            $invoice = $this->invoices->recalculate($invoice);

            FinancialAuditLog::query()->create([
                'request_id' => request()->header('X-Request-ID') ?: (string) Str::uuid(),
                'user_id' => $actor->getKey(),
                'action' => 'invoice.adjustment.set',
                'entity_type' => $invoice->getMorphClass(),
                'entity_id' => $invoice->getKey(),
                'old_values' => $old,
                'new_values' => $invoice->only(['adjustment_amount', 'adjustment_reason', 'total_amount', 'outstanding_amount', 'status']),
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 1000),
                'occurred_at' => now(),
            ]);

            return $invoice;
        }, 3);
    }
}
